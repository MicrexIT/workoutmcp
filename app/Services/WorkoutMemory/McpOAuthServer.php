<?php

namespace App\Services\WorkoutMemory;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use InvalidArgumentException;

class McpOAuthServer
{
    public const Scope = 'mcp:use';

    private const AuthorizationCodeCachePrefix = 'workout_memory.oauth.authorization_code.';

    private const AccessTokenCachePrefix = 'workout_memory.oauth.access_token.';

    private const RefreshTokenCachePrefix = 'workout_memory.oauth.refresh_token.';

    private const ClientCachePrefix = 'workout_memory.oauth.client.';

    private const ApprovalCachePrefix = 'workout_memory.oauth.approval.';

    private const ForbiddenRedirectSchemes = ['javascript', 'data', 'file', 'blob', 'about', 'vbscript'];

    /**
     * @return array<string, mixed>
     */
    public function protectedResourceMetadata(?string $path = null): array
    {
        return [
            'resource' => $this->resourceUrl($path),
            'authorization_servers' => [$this->issuer()],
            'scopes_supported' => [self::Scope],
            'resource_documentation' => $this->publicUrl('/docs'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function authorizationServerMetadata(): array
    {
        return [
            'issuer' => $this->issuer(),
            'authorization_endpoint' => $this->publicUrl('/oauth/authorize'),
            'token_endpoint' => $this->publicUrl('/oauth/token'),
            'registration_endpoint' => $this->publicUrl('/oauth/register'),
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['none'],
            'client_id_metadata_document_supported' => true,
            'scopes_supported' => [self::Scope],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function registerClient(array $input): array
    {
        $redirectUris = collect($this->stringList($input['redirect_uris'] ?? null))
            ->filter(fn (string $redirectUri): bool => $this->isAllowedRedirectUri($redirectUri))
            ->values()
            ->all();

        if ($redirectUris === []) {
            throw new InvalidArgumentException(
                'At least one allowed redirect URI is required: an https URL, an http loopback URL, or a native app scheme.',
            );
        }

        $clientId = 'workout-memory-'.Str::random(48);
        $client = [
            'client_id' => $clientId,
            'client_name' => $this->optionalString($input, 'client_name') ?? 'MCP client',
            'redirect_uris' => $redirectUris,
            'created_at' => now()->toISOString(),
        ];

        Cache::put($this->clientCacheKey($clientId), $client, now()->addDays($this->clientTtlDays()));

        return [
            'client_id' => $clientId,
            'client_id_issued_at' => now()->timestamp,
            'client_name' => $client['client_name'],
            'redirect_uris' => $redirectUris,
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
            'token_endpoint_auth_method' => 'none',
            'scope' => self::Scope,
        ];
    }

    /**
     * Validate the client identity half of an authorization request.
     *
     * Failures here must never redirect back to the client because the redirect URI
     * itself is untrusted, so the controller renders them directly instead.
     *
     * @param  array<string, mixed>  $input
     * @return array{client_id: string, redirect_uri: string, client_name: string}
     */
    public function validateClientForAuthorization(array $input): array
    {
        $clientId = $this->requiredString($input, 'client_id');
        $redirectUri = $this->requiredString($input, 'redirect_uri');

        if (! $this->isKnownClient($clientId, $redirectUri)) {
            throw new InvalidArgumentException('Unknown OAuth client or redirect URI.');
        }

        return [
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'client_name' => $this->clientDisplayName($clientId),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, string|null>
     */
    public function validateAuthorizationRequest(array $input): array
    {
        $client = $this->validateClientForAuthorization($input);

        if ($this->requiredString($input, 'response_type') !== 'code') {
            throw new InvalidArgumentException('Only authorization code responses are supported.');
        }

        if ($this->requiredString($input, 'code_challenge_method') !== 'S256') {
            throw new InvalidArgumentException('Only S256 PKCE challenges are supported.');
        }

        $resource = $this->optionalString($input, 'resource') ?? $this->resourceUrl('mcp/workout-memory');

        if (! hash_equals($this->resourceUrl('mcp/workout-memory'), rtrim($resource, '/'))) {
            throw new InvalidArgumentException('The requested resource is not supported.');
        }

        return [
            'response_type' => 'code',
            'client_id' => $client['client_id'],
            'client_name' => $client['client_name'],
            'redirect_uri' => $client['redirect_uri'],
            'scope' => self::Scope,
            'state' => $this->optionalString($input, 'state'),
            'code_challenge' => $this->requiredString($input, 'code_challenge'),
            'code_challenge_method' => 'S256',
            'resource' => $resource,
        ];
    }

    public function hasRememberedApproval(User $user, string $redirectUri): bool
    {
        $key = $this->approvalCacheKey($user, $redirectUri);

        return $key !== null && Cache::get($key) === true;
    }

    public function rememberApproval(User $user, string $redirectUri): void
    {
        $key = $this->approvalCacheKey($user, $redirectUri);

        if ($key !== null) {
            Cache::put($key, true, now()->addDays($this->approvalTtlDays()));
        }
    }

    /**
     * Describe where an approved authorization will send the user, for the consent screen.
     *
     * @return array{type: 'web'|'loopback'|'native', target: string}
     */
    public function redirectDestination(string $redirectUri): array
    {
        $scheme = strtolower((string) parse_url($redirectUri, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($redirectUri, PHP_URL_HOST));

        if ($scheme === 'https') {
            return ['type' => 'web', 'target' => $host];
        }

        if ($scheme === 'http') {
            $port = parse_url($redirectUri, PHP_URL_PORT);

            return ['type' => 'loopback', 'target' => $host.(is_int($port) ? ':'.$port : '')];
        }

        return ['type' => 'native', 'target' => $scheme.'://'];
    }

    /**
     * @param  array<string, string|null>  $authorizationRequest
     */
    public function issueAuthorizationCode(array $authorizationRequest, User $user): string
    {
        $code = Str::random(96);

        Cache::put($this->authorizationCodeCacheKey($code), [
            'client_id' => $authorizationRequest['client_id'],
            'redirect_uri' => $authorizationRequest['redirect_uri'],
            'scope' => $authorizationRequest['scope'],
            'code_challenge' => $authorizationRequest['code_challenge'],
            'resource' => $authorizationRequest['resource'],
            'user_id' => $user->id,
            'expires_at' => now()->addMinutes($this->authorizationCodeTtlMinutes())->toISOString(),
        ], now()->addMinutes($this->authorizationCodeTtlMinutes()));

        return $code;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function token(array $input): array
    {
        return match ($this->requiredString($input, 'grant_type')) {
            'authorization_code' => $this->redeemAuthorizationCode($input),
            'refresh_token' => $this->redeemRefreshToken($input),
            default => throw new InvalidArgumentException('Only authorization_code and refresh_token grants are supported.'),
        };
    }

    public function userForAccessToken(?string $token, string $requestPath): ?User
    {
        if (! is_string($token) || $token === '') {
            return null;
        }

        $record = Cache::get($this->accessTokenCacheKey($token));

        if (! is_array($record)) {
            return null;
        }

        if (Carbon::parse((string) $record['expires_at'])->isPast()) {
            return null;
        }

        if (! hash_equals($this->resourceUrl($requestPath), (string) $record['resource'])) {
            return null;
        }

        if (! in_array(self::Scope, preg_split('/\s+/', (string) $record['scope']) ?: [], true)) {
            return null;
        }

        return User::query()->find($record['user_id']);
    }

    public function publicUrl(string $path = ''): string
    {
        $baseUrl = rtrim((string) config('workout_memory.oauth.public_url'), '/');

        if ($baseUrl === '') {
            $baseUrl = rtrim((string) config('app.url'), '/');
        }

        return $path === '' ? $baseUrl : $baseUrl.'/'.ltrim($path, '/');
    }

    public function issuer(): string
    {
        return rtrim((string) config('workout_memory.oauth.issuer'), '/') ?: $this->publicUrl();
    }

    public function protectedResourceMetadataUrl(string $path): string
    {
        return $this->publicUrl('/.well-known/oauth-protected-resource/'.ltrim($path, '/'));
    }

    public function resourceUrl(?string $path = null): string
    {
        $path = trim((string) $path, '/');

        return $path === '' ? $this->publicUrl() : $this->publicUrl('/'.$path);
    }

    public function redirectUriWithCode(string $redirectUri, string $code, ?string $state): string
    {
        $query = ['code' => $code];

        if (is_string($state) && $state !== '') {
            $query['state'] = $state;
        }

        return $this->redirectUriWithQuery($redirectUri, $query);
    }

    public function redirectUriWithError(string $redirectUri, string $error, string $description, ?string $state): string
    {
        $query = ['error' => $error, 'error_description' => $description];

        if (is_string($state) && $state !== '') {
            $query['state'] = $state;
        }

        return $this->redirectUriWithQuery($redirectUri, $query);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function redeemAuthorizationCode(array $input): array
    {
        $code = $this->requiredString($input, 'code');
        $record = Cache::pull($this->authorizationCodeCacheKey($code));

        if (! is_array($record)) {
            throw new InvalidArgumentException('The authorization code is invalid or expired.');
        }

        if (Carbon::parse((string) $record['expires_at'])->isPast()) {
            throw new InvalidArgumentException('The authorization code is expired.');
        }

        if (! hash_equals((string) $record['client_id'], $this->requiredString($input, 'client_id'))) {
            throw new InvalidArgumentException('The authorization code does not belong to this client.');
        }

        if (! hash_equals((string) $record['redirect_uri'], $this->requiredString($input, 'redirect_uri'))) {
            throw new InvalidArgumentException('The redirect URI does not match the authorization request.');
        }

        $resource = $this->optionalString($input, 'resource');

        if (is_string($resource) && ! hash_equals((string) $record['resource'], rtrim($resource, '/'))) {
            throw new InvalidArgumentException('The requested resource does not match the authorization request.');
        }

        $codeVerifier = $this->requiredString($input, 'code_verifier');

        if (! hash_equals((string) $record['code_challenge'], $this->pkceChallenge($codeVerifier))) {
            throw new InvalidArgumentException('The PKCE verifier is invalid.');
        }

        return $this->issueTokens($record);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function redeemRefreshToken(array $input): array
    {
        $refreshToken = $this->requiredString($input, 'refresh_token');
        $record = Cache::pull($this->refreshTokenCacheKey($refreshToken));

        if (! is_array($record)) {
            throw new InvalidArgumentException('The refresh token is invalid or expired.');
        }

        if (Carbon::parse((string) $record['expires_at'])->isPast()) {
            throw new InvalidArgumentException('The refresh token is expired.');
        }

        if (! hash_equals((string) $record['client_id'], $this->requiredString($input, 'client_id'))) {
            throw new InvalidArgumentException('The refresh token does not belong to this client.');
        }

        $resource = $this->optionalString($input, 'resource');

        if (is_string($resource) && ! hash_equals((string) $record['resource'], rtrim($resource, '/'))) {
            throw new InvalidArgumentException('The requested resource does not match the refresh token.');
        }

        return $this->issueTokens($record);
    }

    /**
     * Issue a fresh access token plus a rotated single-use refresh token.
     *
     * @param  array<string, mixed>  $grant
     * @return array<string, mixed>
     */
    private function issueTokens(array $grant): array
    {
        $accessToken = 'wm_'.Str::random(96);
        $accessExpiresAt = now()->addMinutes($this->accessTokenTtlMinutes());

        Cache::put($this->accessTokenCacheKey($accessToken), [
            'client_id' => $grant['client_id'],
            'scope' => $grant['scope'],
            'resource' => $grant['resource'],
            'user_id' => $grant['user_id'],
            'expires_at' => $accessExpiresAt->toISOString(),
        ], $accessExpiresAt);

        $refreshToken = 'wmr_'.Str::random(96);
        $refreshExpiresAt = now()->addDays($this->refreshTokenTtlDays());

        Cache::put($this->refreshTokenCacheKey($refreshToken), [
            'client_id' => $grant['client_id'],
            'scope' => $grant['scope'],
            'resource' => $grant['resource'],
            'user_id' => $grant['user_id'],
            'expires_at' => $refreshExpiresAt->toISOString(),
        ], $refreshExpiresAt);

        return [
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => (int) now()->diffInSeconds($accessExpiresAt),
            'refresh_token' => $refreshToken,
            'resource' => (string) $grant['resource'],
            'scope' => (string) $grant['scope'],
        ];
    }

    private function isKnownClient(string $clientId, string $redirectUri): bool
    {
        if (! $this->isAllowedRedirectUri($redirectUri)) {
            return false;
        }

        $registeredClient = Cache::get($this->clientCacheKey($clientId));

        if (is_array($registeredClient)) {
            return $this->redirectUriMatchesRegistered($redirectUri, $registeredClient['redirect_uris'] ?? []);
        }

        return $this->isUrlClientWithTrustedRedirect($clientId, $redirectUri);
    }

    /**
     * Unregistered URL client ids (client id metadata documents, as presented by
     * ChatGPT and newer MCP clients) are accepted when the redirect stays on the
     * client id's own host, or targets a loopback/native handler that the user has
     * to approve on the consent screen anyway.
     */
    private function isUrlClientWithTrustedRedirect(string $clientId, string $redirectUri): bool
    {
        if (! str_starts_with($clientId, 'https://')) {
            return false;
        }

        $clientHost = strtolower((string) parse_url($clientId, PHP_URL_HOST));

        if ($clientHost === '') {
            return false;
        }

        if (strtolower((string) parse_url($redirectUri, PHP_URL_SCHEME)) === 'https') {
            return strtolower((string) parse_url($redirectUri, PHP_URL_HOST)) === $clientHost;
        }

        return true;
    }

    /**
     * @param  array<int, mixed>  $registeredUris
     */
    private function redirectUriMatchesRegistered(string $redirectUri, array $registeredUris): bool
    {
        if (in_array($redirectUri, $registeredUris, true)) {
            return true;
        }

        // RFC 8252 §7.3: loopback redirects may use a different port on every
        // authorization request, so they match their registered URI by host + path.
        $normalized = $this->loopbackRedirectWithoutPort($redirectUri);

        if ($normalized === null) {
            return false;
        }

        foreach ($registeredUris as $registeredUri) {
            if (is_string($registeredUri) && $this->loopbackRedirectWithoutPort($registeredUri) === $normalized) {
                return true;
            }
        }

        return false;
    }

    private function loopbackRedirectWithoutPort(string $redirectUri): ?string
    {
        $parts = parse_url($redirectUri);

        if (! is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'http') {
            return null;
        }

        $host = strtolower($parts['host'] ?? '');

        if (! $this->isLoopbackHost($host)) {
            return null;
        }

        return 'http://'.$host.($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    private function clientDisplayName(string $clientId): string
    {
        $registeredClient = Cache::get($this->clientCacheKey($clientId));
        $registeredName = is_array($registeredClient) ? ($registeredClient['client_name'] ?? null) : null;

        if (is_string($registeredName) && trim($registeredName) !== '') {
            return $registeredName;
        }

        $host = parse_url($clientId, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : 'MCP client';
    }

    /**
     * Allowed redirect targets: hosted https URLs, http loopback URLs on any port
     * (RFC 8252 native apps), and private-use app schemes such as cursor:// or
     * windsurf://. Everything else is rejected.
     */
    private function isAllowedRedirectUri(string $redirectUri): bool
    {
        if (preg_match('/^([a-z][a-z0-9+.\-]*):(.+)$/is', $redirectUri, $matches) !== 1) {
            return false;
        }

        $scheme = strtolower($matches[1]);

        if (in_array($scheme, self::ForbiddenRedirectSchemes, true)) {
            return false;
        }

        if ($scheme === 'https') {
            return (string) parse_url($redirectUri, PHP_URL_HOST) !== '';
        }

        if ($scheme === 'http') {
            return $this->isLoopbackHost(strtolower((string) parse_url($redirectUri, PHP_URL_HOST)));
        }

        return true;
    }

    private function isLoopbackHost(string $host): bool
    {
        return in_array($host, ['localhost', '127.0.0.1', '[::1]'], true);
    }

    /**
     * Approvals are only remembered for hosted https callbacks. Loopback and native
     * scheme redirects can be claimed by any local process, so those clients confirm
     * on the consent screen every time they authorize.
     */
    private function approvalCacheKey(User $user, string $redirectUri): ?string
    {
        $scheme = strtolower((string) parse_url($redirectUri, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($redirectUri, PHP_URL_HOST));

        if ($scheme !== 'https' || $host === '' || $this->isLoopbackHost($host)) {
            return null;
        }

        return self::ApprovalCachePrefix.hash('sha256', $user->id.'|'.$host);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function redirectUriWithQuery(string $redirectUri, array $query): string
    {
        return $redirectUri.(str_contains($redirectUri, '?') ? '&' : '?').http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    private function pkceChallenge(string $codeVerifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function requiredString(array $input, string $key): string
    {
        $value = $input[$key] ?? null;

        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("The {$key} field is required.");
        }

        return trim($value);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function optionalString(array $input, string $key): ?string
    {
        $value = $input[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException("The {$key} field must be a string.");
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @return array<int, string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(fn (mixed $item): bool => is_string($item) && trim($item) !== '')
            ->map(fn (string $item): string => trim($item))
            ->values()
            ->all();
    }

    private function authorizationCodeTtlMinutes(): int
    {
        return max(1, (int) config('workout_memory.oauth.authorization_code_ttl_minutes', 10));
    }

    private function accessTokenTtlMinutes(): int
    {
        return max(1, (int) config('workout_memory.oauth.access_token_ttl_minutes', 1440));
    }

    private function refreshTokenTtlDays(): int
    {
        return max(1, (int) config('workout_memory.oauth.refresh_token_ttl_days', 30));
    }

    private function clientTtlDays(): int
    {
        return max(1, (int) config('workout_memory.oauth.client_ttl_days', 30));
    }

    private function approvalTtlDays(): int
    {
        return max(1, (int) config('workout_memory.oauth.approval_ttl_days', 365));
    }

    private function authorizationCodeCacheKey(string $code): string
    {
        return self::AuthorizationCodeCachePrefix.hash('sha256', $code);
    }

    private function accessTokenCacheKey(string $token): string
    {
        return self::AccessTokenCachePrefix.hash('sha256', $token);
    }

    private function refreshTokenCacheKey(string $token): string
    {
        return self::RefreshTokenCachePrefix.hash('sha256', $token);
    }

    private function clientCacheKey(string $clientId): string
    {
        return self::ClientCachePrefix.hash('sha256', $clientId);
    }
}
