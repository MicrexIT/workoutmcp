<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\WorkoutMemory\CurrentUserResolver;
use App\Services\WorkoutMemory\McpOAuthServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class WorkoutMemoryOAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.url', 'https://workout-memory.test');
        config()->set('workout_memory.oauth.public_url', 'https://workout-memory.test');
        config()->set('workout_memory.oauth.issuer', 'https://workout-memory.test');

        URL::forceRootUrl('https://workout-memory.test');
        URL::forceScheme('https');

        Cache::flush();
        $this->seed();
        app(CurrentUserResolver::class)->user()->markEmailAsVerified();
    }

    public function test_oauth_metadata_is_advertised_for_mcp_clients(): void
    {
        $this->getJson('/.well-known/oauth-protected-resource/mcp/workout-memory')
            ->assertOk()
            ->assertJsonPath('resource', 'https://workout-memory.test/mcp/workout-memory')
            ->assertJsonPath('authorization_servers.0', 'https://workout-memory.test')
            ->assertJsonPath('scopes_supported.0', 'mcp:use')
            ->assertJsonPath('resource_documentation', 'https://workout-memory.test/docs');

        $this->getJson('/.well-known/oauth-authorization-server')
            ->assertOk()
            ->assertJsonPath('issuer', 'https://workout-memory.test')
            ->assertJsonPath('authorization_endpoint', 'https://workout-memory.test/oauth/authorize')
            ->assertJsonPath('token_endpoint', 'https://workout-memory.test/oauth/token')
            ->assertJsonPath('registration_endpoint', 'https://workout-memory.test/oauth/register')
            ->assertJsonPath('grant_types_supported.0', 'authorization_code')
            ->assertJsonPath('grant_types_supported.1', 'refresh_token')
            ->assertJsonPath('client_id_metadata_document_supported', true)
            ->assertJsonPath('token_endpoint_auth_methods_supported.0', 'none');
    }

    public function test_authorization_server_metadata_is_available_at_alternate_discovery_paths(): void
    {
        foreach ([
            '/.well-known/oauth-authorization-server/mcp/workout-memory',
            '/mcp/workout-memory/.well-known/oauth-authorization-server',
            '/.well-known/openid-configuration/mcp/workout-memory',
            '/mcp/workout-memory/.well-known/openid-configuration',
        ] as $path) {
            $this->getJson($path)
                ->assertOk()
                ->assertJsonPath('issuer', 'https://workout-memory.test')
                ->assertJsonPath('authorization_endpoint', 'https://workout-memory.test/oauth/authorize')
                ->assertJsonPath('token_endpoint', 'https://workout-memory.test/oauth/token');
        }
    }

    public function test_chatgpt_url_client_pkce_flow_issues_tokens_through_consent(): void
    {
        $user = app(CurrentUserResolver::class)->user();
        $verifier = str_repeat('a', 64);
        $authorization = $this->authorizationParams(['code_challenge' => $this->pkceChallenge($verifier)]);

        $this->actingAs($user)
            ->get('/oauth/authorize?'.http_build_query($authorization))
            ->assertOk()
            ->assertViewIs('oauth.authorize')
            ->assertSee('chatgpt.com');

        $redirect = $this->approveConsent($user, $authorization)->assertRedirect();
        $query = $this->redirectQuery($redirect, $authorization['redirect_uri']);

        $this->assertArrayHasKey('code', $query);
        $this->assertSame('state-1', $query['state']);

        $tokenResponse = $this->withHeader('Accept', 'application/json')->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $authorization['client_id'],
            'redirect_uri' => $authorization['redirect_uri'],
            'code' => $query['code'],
            'code_verifier' => $verifier,
            'resource' => 'https://workout-memory.test/mcp/workout-memory',
        ])
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('resource', 'https://workout-memory.test/mcp/workout-memory')
            ->assertJsonPath('scope', 'mcp:use')
            ->assertJson(fn ($json) => $json->whereType('expires_in', 'integer')->whereType('refresh_token', 'string')->etc());

        $this->assertMcpAccessWorks((string) $tokenResponse->json('access_token'));
    }

    public function test_remembered_https_approval_skips_consent_on_reauthorization(): void
    {
        $user = app(CurrentUserResolver::class)->user();
        $authorization = $this->authorizationParams();

        $this->actingAs($user)
            ->get('/oauth/authorize?'.http_build_query($authorization))
            ->assertOk()
            ->assertViewIs('oauth.authorize');

        $this->approveConsent($user, $authorization)->assertRedirect();

        $secondRedirect = $this->actingAs($user)
            ->get('/oauth/authorize?'.http_build_query($authorization))
            ->assertRedirect();

        $this->assertArrayHasKey('code', $this->redirectQuery($secondRedirect, $authorization['redirect_uri']));
    }

    public function test_oauth_authorization_requires_login(): void
    {
        app(CurrentUserResolver::class)->user();

        $this->get('/oauth/authorize?'.http_build_query($this->authorizationParams()))
            ->assertRedirect(route('login'));
    }

    public function test_oauth_authorization_requires_verified_email(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get('/oauth/authorize?'.http_build_query($this->authorizationParams()))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_oauth_authorization_request_survives_login_redirect(): void
    {
        $user = User::factory()->create([
            'email' => 'oauth-login@example.com',
            'password' => Hash::make('very-secure-password'),
        ]);
        $authorization = $this->authorizationParams();
        $authorizationPath = '/oauth/authorize?'.http_build_query($authorization);
        $authorizationUrl = 'https://workout-memory.test'.$authorizationPath;

        $this->get($authorizationPath)
            ->assertRedirect(route('login'))
            ->assertSessionHas('url.intended');

        $this->assertSameAuthorizationUrl($authorizationUrl, (string) session('url.intended'));

        $loginRedirect = $this->post(route('login.store'), [
            'email' => 'oauth-login@example.com',
            'password' => 'very-secure-password',
        ])->assertRedirect();

        $this->assertSameAuthorizationUrl($authorizationUrl, (string) $loginRedirect->headers->get('Location'));

        $this->assertAuthenticatedAs($user);

        $this->get($authorizationPath)
            ->assertOk()
            ->assertViewIs('oauth.authorize');

        $redirect = $this->approveConsent($user, $authorization)->assertRedirect();
        $query = $this->redirectQuery($redirect, $authorization['redirect_uri']);

        $this->assertArrayHasKey('code', $query);
        $this->assertSame('state-1', $query['state']);
    }

    public function test_mcp_endpoint_challenges_missing_oauth_token(): void
    {
        $response = $this->postJson('/mcp/workout-memory', [])
            ->assertUnauthorized();

        $this->assertStringContainsString(
            'resource_metadata="https://workout-memory.test/.well-known/oauth-protected-resource/mcp/workout-memory"',
            (string) $response->headers->get('WWW-Authenticate'),
        );
        $this->assertStringNotContainsString(
            'resource_metadata="http://workout-memory.test',
            (string) $response->headers->get('WWW-Authenticate'),
        );
    }

    public function test_mcp_endpoint_requires_verified_token_owner(): void
    {
        $user = User::factory()->unverified()->create();
        $verifier = str_repeat('z', 64);
        $authorization = $this->authorizationParams([
            'code_challenge' => $this->pkceChallenge($verifier),
        ]);
        $code = app(McpOAuthServer::class)->issueAuthorizationCode($authorization, $user);
        $tokens = app(McpOAuthServer::class)->token([
            'grant_type' => 'authorization_code',
            'client_id' => $authorization['client_id'],
            'redirect_uri' => $authorization['redirect_uri'],
            'code' => $code,
            'code_verifier' => $verifier,
            'resource' => 'https://workout-memory.test/mcp/workout-memory',
        ]);

        $this->withToken((string) $tokens['access_token'])
            ->postJson('/mcp/workout-memory', $this->initializePayload())
            ->assertForbidden()
            ->assertJsonPath('message', 'Your email address is not verified.');
    }

    public function test_mcp_endpoint_rate_limits_missing_oauth_tokens(): void
    {
        config()->set('workout_memory.rate_limits.mcp_unauthenticated_per_minute', 1);

        $this->postJson('/mcp/workout-memory', [])->assertUnauthorized();
        $this->postJson('/mcp/workout-memory', [])->assertStatus(429);
    }

    public function test_dynamic_client_registration_accepts_mcp_client_redirect_uris(): void
    {
        foreach ([
            'Claude' => 'https://claude.ai/api/mcp/auth_callback',
            'Claude (claude.com)' => 'https://claude.com/api/mcp/auth_callback',
            'ChatGPT' => 'https://chatgpt.com/connector_platform_oauth_redirect',
            'Cursor' => 'cursor://anysphere.cursor-mcp/oauth/callback',
            'Windsurf' => 'windsurf://oauth/callback',
            'VS Code' => 'https://vscode.dev/redirect',
            'VS Code loopback' => 'http://127.0.0.1:33418/',
            'Claude Code' => 'http://localhost:53682/callback',
            'IPv6 loopback client' => 'http://[::1]:8976/oauth/callback',
        ] as $clientName => $redirectUri) {
            $this->postJson('/oauth/register', [
                'client_name' => $clientName,
                'redirect_uris' => [$redirectUri],
            ])
                ->assertCreated()
                ->assertJsonPath('redirect_uris.0', $redirectUri)
                ->assertJsonPath('token_endpoint_auth_method', 'none')
                ->assertJsonPath('grant_types.1', 'refresh_token')
                ->assertJsonPath('scope', 'mcp:use');
        }
    }

    public function test_dynamic_client_registration_filters_disallowed_redirect_uris(): void
    {
        $this->postJson('/oauth/register', [
            'client_name' => 'Mixed client',
            'redirect_uris' => [
                'http://attacker.example/callback',
                'javascript:alert(1)',
                'https://claude.ai/api/mcp/auth_callback',
            ],
        ])
            ->assertCreated()
            ->assertJsonCount(1, 'redirect_uris')
            ->assertJsonPath('redirect_uris.0', 'https://claude.ai/api/mcp/auth_callback');

        $this->postJson('/oauth/register', [
            'client_name' => 'Hostile client',
            'redirect_uris' => [
                'http://attacker.example/callback',
                'javascript:alert(1)',
                'data:text/html,x',
            ],
        ])
            ->assertStatus(400)
            ->assertJsonPath('error', 'invalid_client_metadata');
    }

    public function test_claude_web_client_completes_full_flow_with_dynamic_registration(): void
    {
        $user = app(CurrentUserResolver::class)->user();
        $clientId = $this->registerClient('Claude', ['https://claude.ai/api/mcp/auth_callback']);
        $verifier = str_repeat('b', 64);
        $authorization = $this->authorizationParams([
            'client_id' => $clientId,
            'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback',
            'code_challenge' => $this->pkceChallenge($verifier),
        ]);

        $this->actingAs($user)
            ->get('/oauth/authorize?'.http_build_query($authorization))
            ->assertOk()
            ->assertViewIs('oauth.authorize')
            ->assertSee('Claude');

        $redirect = $this->approveConsent($user, $authorization)->assertRedirect();
        $query = $this->redirectQuery($redirect, 'https://claude.ai/api/mcp/auth_callback');

        $tokenResponse = $this->withHeader('Accept', 'application/json')->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback',
            'code' => $query['code'],
            'code_verifier' => $verifier,
        ])->assertOk();

        $this->assertMcpAccessWorks((string) $tokenResponse->json('access_token'));

        $this->actingAs($user)
            ->get('/oauth/authorize?'.http_build_query($authorization))
            ->assertRedirect();
    }

    public function test_loopback_client_reauthorizes_with_consent_and_any_port(): void
    {
        $user = app(CurrentUserResolver::class)->user();
        $clientId = $this->registerClient('Claude Code', ['http://localhost:33418/callback']);
        $verifier = str_repeat('c', 64);
        $redirectUri = 'http://localhost:51515/callback';
        $authorization = $this->authorizationParams([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'code_challenge' => $this->pkceChallenge($verifier),
        ]);

        $this->actingAs($user)
            ->get('/oauth/authorize?'.http_build_query($authorization))
            ->assertOk()
            ->assertViewIs('oauth.authorize')
            ->assertSee('localhost:51515');

        $redirect = $this->approveConsent($user, $authorization)->assertRedirect();
        $query = $this->redirectQuery($redirect, $redirectUri);

        $tokenResponse = $this->withHeader('Accept', 'application/json')->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'code' => $query['code'],
            'code_verifier' => $verifier,
        ])->assertOk();

        $this->assertMcpAccessWorks((string) $tokenResponse->json('access_token'));

        $this->actingAs($user)
            ->get('/oauth/authorize?'.http_build_query($authorization))
            ->assertOk()
            ->assertViewIs('oauth.authorize');
    }

    public function test_custom_scheme_client_completes_authorization(): void
    {
        $user = app(CurrentUserResolver::class)->user();
        $redirectUri = 'cursor://anysphere.cursor-mcp/oauth/callback';
        $clientId = $this->registerClient('Cursor', [$redirectUri]);
        $verifier = str_repeat('d', 64);
        $authorization = $this->authorizationParams([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'code_challenge' => $this->pkceChallenge($verifier),
        ]);

        $this->actingAs($user)
            ->get('/oauth/authorize?'.http_build_query($authorization))
            ->assertOk()
            ->assertViewIs('oauth.authorize')
            ->assertSee('cursor://');

        $redirect = $this->approveConsent($user, $authorization)->assertRedirect();
        $query = $this->redirectQuery($redirect, $redirectUri);

        $this->withHeader('Accept', 'application/json')->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'code' => $query['code'],
            'code_verifier' => $verifier,
        ])->assertOk();
    }

    public function test_denying_consent_redirects_with_access_denied(): void
    {
        $user = app(CurrentUserResolver::class)->user();
        $authorization = $this->authorizationParams();

        $redirect = $this->actingAs($user)
            ->post(route('workout-memory.oauth.authorize.decision'), [...$authorization, 'action' => 'deny'])
            ->assertRedirect();

        $query = $this->redirectQuery($redirect, $authorization['redirect_uri']);

        $this->assertSame('access_denied', $query['error']);
        $this->assertSame('state-1', $query['state']);
        $this->assertArrayNotHasKey('code', $query);
    }

    public function test_refresh_token_grant_rotates_tokens(): void
    {
        $user = app(CurrentUserResolver::class)->user();
        $verifier = str_repeat('e', 64);
        $authorization = $this->authorizationParams(['code_challenge' => $this->pkceChallenge($verifier)]);

        $this->actingAs($user)->get('/oauth/authorize?'.http_build_query($authorization))->assertOk();
        $redirect = $this->approveConsent($user, $authorization)->assertRedirect();
        $query = $this->redirectQuery($redirect, $authorization['redirect_uri']);

        $initialTokens = $this->withHeader('Accept', 'application/json')->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $authorization['client_id'],
            'redirect_uri' => $authorization['redirect_uri'],
            'code' => $query['code'],
            'code_verifier' => $verifier,
        ])->assertOk();

        $refreshToken = (string) $initialTokens->json('refresh_token');

        $refreshedTokens = $this->withHeader('Accept', 'application/json')->post('/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $authorization['client_id'],
            'refresh_token' => $refreshToken,
        ])
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('scope', 'mcp:use');

        $this->assertNotSame($initialTokens->json('access_token'), $refreshedTokens->json('access_token'));
        $this->assertNotSame($refreshToken, $refreshedTokens->json('refresh_token'));
        $this->assertMcpAccessWorks((string) $refreshedTokens->json('access_token'));

        $this->withHeader('Accept', 'application/json')->post('/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $authorization['client_id'],
            'refresh_token' => $refreshToken,
        ])
            ->assertStatus(400)
            ->assertJsonPath('error', 'invalid_grant');

        $this->withHeader('Accept', 'application/json')->post('/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $authorization['client_id'],
            'refresh_token' => (string) $refreshedTokens->json('refresh_token'),
        ])->assertOk();
    }

    public function test_invalid_pkce_method_redirects_error_to_client(): void
    {
        $user = app(CurrentUserResolver::class)->user();
        $authorization = $this->authorizationParams(['code_challenge_method' => 'plain']);

        $redirect = $this->actingAs($user)
            ->get('/oauth/authorize?'.http_build_query($authorization))
            ->assertRedirect();

        $query = $this->redirectQuery($redirect, $authorization['redirect_uri']);

        $this->assertSame('invalid_request', $query['error']);
        $this->assertSame('state-1', $query['state']);
        $this->assertArrayNotHasKey('code', $query);
    }

    public function test_unknown_client_is_rejected_without_redirect(): void
    {
        $user = app(CurrentUserResolver::class)->user();
        $authorization = $this->authorizationParams([
            'client_id' => 'not-a-registered-client',
            'redirect_uri' => 'https://attacker.example/callback',
        ]);

        $this->actingAs($user)
            ->get('/oauth/authorize?'.http_build_query($authorization))
            ->assertStatus(400)
            ->assertJsonPath('error', 'invalid_request');
    }

    public function test_url_client_with_mismatched_https_redirect_is_rejected(): void
    {
        $user = app(CurrentUserResolver::class)->user();
        $authorization = $this->authorizationParams([
            'client_id' => 'https://claude.ai/.well-known/client-metadata.json',
            'redirect_uri' => 'https://attacker.example/callback',
        ]);

        $this->actingAs($user)
            ->get('/oauth/authorize?'.http_build_query($authorization))
            ->assertStatus(400)
            ->assertJsonPath('error', 'invalid_request');
    }

    public function test_url_client_with_loopback_redirect_is_allowed_via_consent(): void
    {
        $user = app(CurrentUserResolver::class)->user();
        $verifier = str_repeat('f', 64);
        $redirectUri = 'http://127.0.0.1:43110/oauth/callback';
        $authorization = $this->authorizationParams([
            'client_id' => 'https://claude.ai/.well-known/client-metadata.json',
            'redirect_uri' => $redirectUri,
            'code_challenge' => $this->pkceChallenge($verifier),
        ]);

        $this->actingAs($user)
            ->get('/oauth/authorize?'.http_build_query($authorization))
            ->assertOk()
            ->assertViewIs('oauth.authorize')
            ->assertSee('127.0.0.1:43110');

        $redirect = $this->approveConsent($user, $authorization)->assertRedirect();

        $this->assertArrayHasKey('code', $this->redirectQuery($redirect, $redirectUri));
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function authorizationParams(array $overrides = []): array
    {
        return [
            'response_type' => 'code',
            'client_id' => 'https://chatgpt.com/oauth/workout-memory/client.json',
            'redirect_uri' => 'https://chatgpt.com/connector/oauth/test-callback',
            'scope' => 'mcp:use',
            'state' => 'state-1',
            'code_challenge' => $this->pkceChallenge(str_repeat('a', 64)),
            'code_challenge_method' => 'S256',
            'resource' => 'https://workout-memory.test/mcp/workout-memory',
            ...$overrides,
        ];
    }

    /**
     * @param  array<int, string>  $redirectUris
     */
    private function registerClient(string $clientName, array $redirectUris): string
    {
        return (string) $this->postJson('/oauth/register', [
            'client_name' => $clientName,
            'redirect_uris' => $redirectUris,
        ])->assertCreated()->json('client_id');
    }

    /**
     * @param  array<string, string>  $authorization
     */
    private function approveConsent(User $user, array $authorization): TestResponse
    {
        return $this->actingAs($user)->post(
            route('workout-memory.oauth.authorize.decision'),
            [...$authorization, 'action' => 'approve'],
        );
    }

    /**
     * @return array<string, string>
     */
    private function redirectQuery(TestResponse $redirect, string $expectedRedirectUri): array
    {
        $location = (string) $redirect->headers->get('Location');
        $this->assertStringStartsWith($expectedRedirectUri, $location);

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        return $query;
    }

    private function assertMcpAccessWorks(string $accessToken): void
    {
        $this->withToken($accessToken)
            ->postJson('/mcp/workout-memory', $this->initializePayload())
            ->assertOk()
            ->assertJsonPath('result.serverInfo.name', 'Workout Memory Server');
    }

    /**
     * @return array<string, mixed>
     */
    private function initializePayload(): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-11-25',
                'capabilities' => [],
                'clientInfo' => [
                    'name' => 'phpunit',
                    'version' => '1.0.0',
                ],
            ],
        ];
    }

    private function pkceChallenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    private function assertSameAuthorizationUrl(string $expected, string $actual): void
    {
        $this->assertSame(parse_url($expected, PHP_URL_SCHEME), parse_url($actual, PHP_URL_SCHEME));
        $this->assertSame(parse_url($expected, PHP_URL_HOST), parse_url($actual, PHP_URL_HOST));
        $this->assertSame(parse_url($expected, PHP_URL_PATH), parse_url($actual, PHP_URL_PATH));

        parse_str((string) parse_url($expected, PHP_URL_QUERY), $expectedQuery);
        parse_str((string) parse_url($actual, PHP_URL_QUERY), $actualQuery);

        ksort($expectedQuery);
        ksort($actualQuery);

        $this->assertSame($expectedQuery, $actualQuery);
    }
}
