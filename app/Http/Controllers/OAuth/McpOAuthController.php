<?php

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\Services\WorkoutMemory\CurrentUserResolver;
use App\Services\WorkoutMemory\McpOAuthServer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class McpOAuthController extends Controller
{
    public function protectedResource(McpOAuthServer $oauth, ?string $path = null): JsonResponse
    {
        return response()->json($oauth->protectedResourceMetadata($path));
    }

    public function authorizationServer(McpOAuthServer $oauth): JsonResponse
    {
        return response()->json($oauth->authorizationServerMetadata());
    }

    public function register(Request $request, McpOAuthServer $oauth): JsonResponse
    {
        try {
            return response()->json($oauth->registerClient($request->all()), 201);
        } catch (InvalidArgumentException $exception) {
            return $this->oauthError('invalid_client_metadata', $exception->getMessage());
        }
    }

    public function authorize(Request $request, McpOAuthServer $oauth, CurrentUserResolver $users): View|RedirectResponse|JsonResponse
    {
        $authorization = $this->validatedAuthorization($request->query(), $oauth);

        if (! is_array($authorization)) {
            return $authorization;
        }

        if ($oauth->hasRememberedApproval($users->user(), (string) $authorization['redirect_uri'])) {
            return $this->redirectWithFreshCode($authorization, $oauth, $users);
        }

        return view('oauth.authorize', [
            'authorization' => $authorization,
            'destination' => $oauth->redirectDestination((string) $authorization['redirect_uri']),
        ]);
    }

    public function decide(Request $request, McpOAuthServer $oauth, CurrentUserResolver $users): RedirectResponse|JsonResponse
    {
        $authorization = $this->validatedAuthorization($request->all(), $oauth);

        if (! is_array($authorization)) {
            return $authorization;
        }

        if ($request->input('action') !== 'approve') {
            return redirect()->away($oauth->redirectUriWithError(
                (string) $authorization['redirect_uri'],
                'access_denied',
                'The user denied the authorization request.',
                $authorization['state'],
            ));
        }

        $oauth->rememberApproval($users->user(), (string) $authorization['redirect_uri']);

        return $this->redirectWithFreshCode($authorization, $oauth, $users);
    }

    public function token(Request $request, McpOAuthServer $oauth): JsonResponse
    {
        try {
            return response()->json($oauth->token($request->all()));
        } catch (InvalidArgumentException $exception) {
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
    private function validatedAuthorization(array $input, McpOAuthServer $oauth): array|RedirectResponse|JsonResponse
    {
        try {
            $client = $oauth->validateClientForAuthorization($input);
        } catch (InvalidArgumentException $exception) {
            return $this->oauthError('invalid_request', $exception->getMessage());
        }

        try {
            return $oauth->validateAuthorizationRequest($input);
        } catch (InvalidArgumentException $exception) {
            $state = $input['state'] ?? null;

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
    private function redirectWithFreshCode(array $authorization, McpOAuthServer $oauth, CurrentUserResolver $users): RedirectResponse
    {
        $code = $oauth->issueAuthorizationCode($authorization, $users->user());

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
}
