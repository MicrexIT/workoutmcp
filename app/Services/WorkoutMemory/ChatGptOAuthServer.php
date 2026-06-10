<?php

namespace App\Services\WorkoutMemory;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ChatGptOAuthServer
{
    public const Scope = 'mcp:use';

    private const AuthorizationCodeCachePrefix = 'workout_memory.oauth.authorization_code.';

    private const AccessTokenCachePrefix = 'workout_memory.oauth.access_token.';

    private const ClientCachePrefix = 'workout_memory.oauth.client.';

    /**
     * @return array<string, mixed>
     */
    public function protectedResourceMetadata(?string $path = null): array
    {
        return [
            'resource' => $this->resourceUrl($path),
            'authorization_servers' => [$this->issuer()],
            'scopes_supported' => [self::Scope],
            'resource_documentation' => $this->publicUrl('/'),
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
            'grant_types_supported' => ['authorization_code'],
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
        $redirectUris = $this->stringList($input['redirect_uris'] ?? null);

        if ($redirectUris === []) {
            throw new InvalidArgumentException('At least one redirect URI is required.');
        }

        foreach ($redirectUris as $redirectUri) {
            if (! $this->isAllowedRedirectUri($redirectUri)) {
                throw new InvalidArgumentException('The redirect URI is not allowed.');
            }
        }

        $clientId = 'workout-memory-'.Str::random(48);
        $client = [
            'client_id' => $clientId,
            'client_name' => $this->optionalString($input, 'client_name') ?? 'ChatGPT',
            'redirect_uris' => $redirectUris,
            'created_at' => now()->toISOString(),
        ];

        Cache::put($this->clientCacheKey($clientId), $client, now()->addDays($this->clientTtlDays()));

        return [
            'client_id' => $clientId,
            'client_id_issued_at' => now()->timestamp,
            'client_name' => $client['client_name'],
            'redirect_uris' => $redirectUris,
            'grant_types' => ['authorization_code'],
            'response_types' => ['code'],
            'token_endpoint_auth_method' => 'none',
            'scope' => self::Scope,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, string|null>
     */
    public function validateAuthorizationRequest(array $input): array
    {
        if ($this->requiredString($input, 'response_type') !== 'code') {
            throw new InvalidArgumentException('Only authorization code responses are supported.');
        }

        if ($this->requiredString($input, 'code_challenge_method') !== 'S256') {
            throw new InvalidArgumentException('Only S256 PKCE challenges are supported.');
        }

        $clientId = $this->requiredString($input, 'client_id');
        $redirectUri = $this->requiredString($input, 'redirect_uri');

        if (! $this->isKnownClient($clientId, $redirectUri)) {
            throw new InvalidArgumentException('Unknown OAuth client or redirect URI.');
        }

        $scope = $this->normalizeScope($this->optionalString($input, 'scope'));
        $resource = $this->optionalString($input, 'resource') ?? $this->resourceUrl('mcp/workout-memory');

        if (! hash_equals($this->resourceUrl('mcp/workout-memory'), rtrim($resource, '/'))) {
            throw new InvalidArgumentException('The requested resource is not supported.');
        }

        return [
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => $scope,
            'state' => $this->optionalString($input, 'state'),
            'code_challenge' => $this->requiredString($input, 'code_challenge'),
            'code_challenge_method' => 'S256',
            'resource' => $resource,
        ];
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
    public function redeemAuthorizationCode(array $input): array
    {
        if ($this->requiredString($input, 'grant_type') !== 'authorization_code') {
            throw new InvalidArgumentException('Only authorization_code grants are supported.');
        }

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

        $token = 'wm_'.Str::random(96);
        $expiresAt = now()->addMinutes($this->accessTokenTtlMinutes());

        Cache::put($this->accessTokenCacheKey($token), [
            'client_id' => $record['client_id'],
            'scope' => $record['scope'],
            'resource' => $record['resource'],
            'user_id' => $record['user_id'],
            'expires_at' => $expiresAt->toISOString(),
        ], $expiresAt);

        return [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => (int) now()->diffInSeconds($expiresAt),
            'resource' => (string) $record['resource'],
            'scope' => $record['scope'],
        ];
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

        return $redirectUri.(str_contains($redirectUri, '?') ? '&' : '?').http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    private function normalizeScope(?string $scope): string
    {
        $scope = trim($scope ?? self::Scope);
        $scopes = preg_split('/\s+/', $scope) ?: [];

        if (! in_array(self::Scope, $scopes, true)) {
            throw new InvalidArgumentException('The mcp:use scope is required.');
        }

        return self::Scope;
    }

    private function isKnownClient(string $clientId, string $redirectUri): bool
    {
        if (! $this->isAllowedRedirectUri($redirectUri)) {
            return false;
        }

        $registeredClient = Cache::get($this->clientCacheKey($clientId));

        if (is_array($registeredClient)) {
            return in_array($redirectUri, $registeredClient['redirect_uris'] ?? [], true);
        }

        $clientHost = parse_url($clientId, PHP_URL_HOST);

        return is_string($clientHost)
            && in_array($clientHost, ['chatgpt.com', 'chat.openai.com'], true)
            && str_starts_with($clientId, 'https://');
    }

    private function isAllowedRedirectUri(string $redirectUri): bool
    {
        if (filter_var($redirectUri, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = parse_url($redirectUri, PHP_URL_SCHEME);
        $host = parse_url($redirectUri, PHP_URL_HOST);

        if (! is_string($scheme) || ! is_string($host)) {
            return false;
        }

        if ($scheme === 'https' && in_array($host, ['chatgpt.com', 'chat.openai.com'], true)) {
            return true;
        }

        return $scheme === 'http' && in_array($host, ['localhost', '127.0.0.1'], true);
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

    private function clientTtlDays(): int
    {
        return max(1, (int) config('workout_memory.oauth.client_ttl_days', 30));
    }

    private function authorizationCodeCacheKey(string $code): string
    {
        return self::AuthorizationCodeCachePrefix.hash('sha256', $code);
    }

    private function accessTokenCacheKey(string $token): string
    {
        return self::AccessTokenCachePrefix.hash('sha256', $token);
    }

    private function clientCacheKey(string $clientId): string
    {
        return self::ClientCachePrefix.hash('sha256', $clientId);
    }
}
