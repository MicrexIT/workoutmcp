<?php

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\WorkoutMemory\CurrentUserResolver;
use App\Services\WorkoutMemory\McpOAuthServer;
use App\Services\WorkoutMemory\WorkoutMemoryActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class McpOAuthController extends Controller
{
    public function protectedResource(Request $request, McpOAuthServer $oauth, WorkoutMemoryActivityLogger $activity, ?string $path = null): JsonResponse
    {
        $activity->info('oauth.discovery.protected_resource_served', [
            'resource_path' => $path,
        ], $request);

        return response()->json($oauth->protectedResourceMetadata($path));
    }

    public function authorizationServer(Request $request, McpOAuthServer $oauth, WorkoutMemoryActivityLogger $activity): JsonResponse
    {
        $activity->info('oauth.discovery.authorization_server_served', [], $request);

        return response()->json($oauth->authorizationServerMetadata());
    }

    public function register(Request $request, McpOAuthServer $oauth, WorkoutMemoryActivityLogger $activity): JsonResponse
    {
        try {
            $client = $oauth->registerClient($request->all());

            $activity->info('oauth.client.registered', [
                ...$activity->oauthClientContext(
                    (string) $client['client_id'],
                    is_array($client['redirect_uris'] ?? null) ? ($client['redirect_uris'][0] ?? null) : null,
                    is_string($client['client_name'] ?? null) ? $client['client_name'] : null,
                ),
                'redirect_uri_count' => count($client['redirect_uris'] ?? []),
                'grant_types' => $client['grant_types'] ?? [],
            ], $request);

            return response()->json($client, 201);
        } catch (InvalidArgumentException $exception) {
            $activity->warning('oauth.client_registration.failed', [
                ...$this->redirectUrisContext($request->input('redirect_uris'), $activity),
                'failure_reason' => $exception->getMessage(),
            ], $request);

            return $this->oauthError('invalid_client_metadata', $exception->getMessage());
        }
    }

    public function authorize(Request $request, McpOAuthServer $oauth, CurrentUserResolver $users, WorkoutMemoryActivityLogger $activity): View|RedirectResponse|JsonResponse
    {
        $authorization = $this->validatedAuthorization($request->query(), $oauth, $activity, $request);

        if (! is_array($authorization)) {
            return $authorization;
        }

        $user = $users->user();

        if ($oauth->hasRememberedApproval($user, (string) $authorization['redirect_uri'])) {
            $activity->info('oauth.authorization.auto_approved', $this->authorizationContext($authorization, $user, $activity), $request);

            return $this->redirectWithFreshCode($authorization, $oauth, $users, $activity, $request, 'remembered_approval');
        }

        $activity->info('oauth.authorization.consent_shown', $this->authorizationContext($authorization, $user, $activity), $request);

        return view('oauth.authorize', [
            'authorization' => $authorization,
            'destination' => $oauth->redirectDestination((string) $authorization['redirect_uri']),
        ]);
    }

    public function decide(Request $request, McpOAuthServer $oauth, CurrentUserResolver $users, WorkoutMemoryActivityLogger $activity): RedirectResponse|JsonResponse
    {
        $authorization = $this->validatedAuthorization($request->all(), $oauth, $activity, $request);

        if (! is_array($authorization)) {
            return $authorization;
        }

        $user = $users->user();

        if ($request->input('action') !== 'approve') {
            $activity->info('oauth.authorization.denied', $this->authorizationContext($authorization, $user, $activity), $request);

            return redirect()->away($oauth->redirectUriWithError(
                (string) $authorization['redirect_uri'],
                'access_denied',
                'The user denied the authorization request.',
                $authorization['state'],
            ));
        }

        $activity->info('oauth.authorization.approved', $this->authorizationContext($authorization, $user, $activity), $request);

        $oauth->rememberApproval($user, (string) $authorization['redirect_uri']);

        return $this->redirectWithFreshCode($authorization, $oauth, $users, $activity, $request, 'fresh_approval');
    }

    public function token(Request $request, McpOAuthServer $oauth, WorkoutMemoryActivityLogger $activity): JsonResponse
    {
        try {
            $token = $oauth->token($request->all());

            $activity->info('oauth.token.issued', [
                ...$activity->oauthClientContext($this->stringInput($request, 'client_id')),
                'grant_type' => $this->stringInput($request, 'grant_type'),
                'resource' => $token['resource'] ?? null,
                'scope' => $token['scope'] ?? null,
            ], $request);

            return response()->json($token);
        } catch (InvalidArgumentException $exception) {
            $activity->warning('oauth.token.failed', [
                ...$activity->oauthClientContext($this->stringInput($request, 'client_id')),
                'grant_type' => $this->stringInput($request, 'grant_type'),
                'failure_reason' => $exception->getMessage(),
            ], $request);

            return $this->oauthError('invalid_grant', $exception->getMessage());
        }
    }

    /**
     * Validate an authorization request in two phases: client identity failures render
     * directly (the redirect URI is untrusted), later failures redirect back to the
     * client with a standard OAuth error so it can surface the problem to the user.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, string|null>|RedirectResponse|JsonResponse
     */
    private function validatedAuthorization(array $input, McpOAuthServer $oauth, WorkoutMemoryActivityLogger $activity, Request $request): array|RedirectResponse|JsonResponse
    {
        try {
            $client = $oauth->validateClientForAuthorization($input);
        } catch (InvalidArgumentException $exception) {
            $activity->warning('oauth.authorization.invalid_client', [
                ...$activity->oauthClientContext(
                    $this->stringFromArray($input, 'client_id'),
                    $this->stringFromArray($input, 'redirect_uri'),
                ),
                'failure_reason' => $exception->getMessage(),
            ], $request);

            return $this->oauthError('invalid_request', $exception->getMessage());
        }

        try {
            return $oauth->validateAuthorizationRequest($input);
        } catch (InvalidArgumentException $exception) {
            $state = $input['state'] ?? null;

            $activity->warning('oauth.authorization.invalid_request', [
                ...$activity->oauthClientContext(
                    $client['client_id'],
                    $client['redirect_uri'],
                    $client['client_name'],
                ),
                'has_state' => is_string($state) && $state !== '',
                'failure_reason' => $exception->getMessage(),
            ], $request);

            return redirect()->away($oauth->redirectUriWithError(
                $client['redirect_uri'],
                'invalid_request',
                $exception->getMessage(),
                is_string($state) ? $state : null,
            ));
        }
    }

    /**
     * @param  array<string, string|null>  $authorization
     */
    private function redirectWithFreshCode(array $authorization, McpOAuthServer $oauth, CurrentUserResolver $users, WorkoutMemoryActivityLogger $activity, Request $request, string $approvalSource): RedirectResponse
    {
        $user = $users->user();
        $code = $oauth->issueAuthorizationCode($authorization, $user);

        $activity->info('oauth.authorization_code.issued', [
            ...$this->authorizationContext($authorization, $user, $activity),
            'approval_source' => $approvalSource,
        ], $request);

        return redirect()->away($oauth->redirectUriWithCode(
            (string) $authorization['redirect_uri'],
            $code,
            $authorization['state'],
        ));
    }

    private function oauthError(string $error, string $description, int $status = 400): JsonResponse
    {
        return response()->json([
            'error' => $error,
            'error_description' => $description,
        ], $status);
    }

    /**
     * @param  array<string, string|null>  $authorization
     * @return array<string, mixed>
     */
    private function authorizationContext(array $authorization, User $user, WorkoutMemoryActivityLogger $activity): array
    {
        return [
            ...$activity->userContext($user),
            ...$activity->oauthClientContext(
                $authorization['client_id'] ?? null,
                $authorization['redirect_uri'] ?? null,
                $authorization['client_name'] ?? null,
            ),
            'scope' => $authorization['scope'] ?? null,
            'resource' => $authorization['resource'] ?? null,
            'has_state' => is_string($authorization['state'] ?? null) && $authorization['state'] !== '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function redirectUrisContext(mixed $redirectUris, WorkoutMemoryActivityLogger $activity): array
    {
        $redirectUris = is_array($redirectUris)
            ? collect($redirectUris)->filter(fn (mixed $uri): bool => is_string($uri) && trim($uri) !== '')->values()
            : collect();

        return [
            'redirect_uri_count' => $redirectUris->count(),
            'redirect_destinations' => $redirectUris
                ->map(fn (string $uri): ?array => $activity->oauthClientContext(null, $uri)['redirect_destination'] ?? null)
                ->filter()
                ->values()
                ->all(),
        ];
    }

    private function stringInput(Request $request, string $key): ?string
    {
        $value = $request->input($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function stringFromArray(array $input, string $key): ?string
    {
        $value = $input[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
