<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\WorkoutMemory\CurrentUserResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
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
    }

    public function test_oauth_metadata_is_advertised_for_chatgpt(): void
    {
        $this->getJson('/.well-known/oauth-protected-resource/mcp/workout-memory')
            ->assertOk()
            ->assertJsonPath('resource', 'https://workout-memory.test/mcp/workout-memory')
            ->assertJsonPath('authorization_servers.0', 'https://workout-memory.test')
            ->assertJsonPath('scopes_supported.0', 'mcp:use');

        $this->getJson('/.well-known/oauth-authorization-server')
            ->assertOk()
            ->assertJsonPath('issuer', 'https://workout-memory.test')
            ->assertJsonPath('authorization_endpoint', 'https://workout-memory.test/oauth/authorize')
            ->assertJsonPath('token_endpoint', 'https://workout-memory.test/oauth/token')
            ->assertJsonPath('client_id_metadata_document_supported', true)
            ->assertJsonPath('token_endpoint_auth_methods_supported.0', 'none');
    }

    public function test_authorization_server_metadata_is_available_at_chatgpt_discovery_paths(): void
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

    public function test_chatgpt_oauth_pkce_flow_issues_token_that_allows_mcp_requests(): void
    {
        $user = app(CurrentUserResolver::class)->user();
        $clientId = 'https://chatgpt.com/oauth/workout-memory/client.json';
        $redirectUri = 'https://chatgpt.com/connector/oauth/test-callback';
        $verifier = str_repeat('a', 64);
        $challenge = $this->pkceChallenge($verifier);

        $authorization = [
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => 'mcp:use',
            'state' => 'state-1',
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
            'resource' => 'https://workout-memory.test/mcp/workout-memory',
        ];

        $redirect = $this->actingAs($user)
            ->get('/oauth/authorize?'.http_build_query($authorization))
            ->assertRedirect();

        $location = (string) $redirect->headers->get('Location');
        $this->assertStringStartsWith($redirectUri, $location);

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertArrayHasKey('code', $query);
        $this->assertSame('state-1', $query['state']);

        $tokenResponse = $this->withHeader('Accept', 'application/json')->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'code' => $query['code'],
            'code_verifier' => $verifier,
            'resource' => 'https://workout-memory.test/mcp/workout-memory',
        ])
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('resource', 'https://workout-memory.test/mcp/workout-memory')
            ->assertJsonPath('scope', 'mcp:use')
            ->assertJson(fn ($json) => $json->whereType('expires_in', 'integer')->etc());

        $token = $tokenResponse->json('access_token');

        $this->withToken((string) $token)
            ->postJson('/mcp/workout-memory', [
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
            ])
            ->assertOk()
            ->assertJsonPath('result.serverInfo.name', 'Workout Memory Server');
    }

    public function test_oauth_authorization_requires_login(): void
    {
        app(CurrentUserResolver::class)->user();

        $authorization = [
            'response_type' => 'code',
            'client_id' => 'https://chatgpt.com/oauth/workout-memory/client.json',
            'redirect_uri' => 'https://chatgpt.com/connector/oauth/test-callback',
            'scope' => 'mcp:use',
            'code_challenge' => $this->pkceChallenge(str_repeat('a', 64)),
            'code_challenge_method' => 'S256',
            'resource' => 'https://workout-memory.test/mcp/workout-memory',
        ];

        $this->get('/oauth/authorize?'.http_build_query($authorization))
            ->assertRedirect(route('login'));
    }

    public function test_oauth_authorization_request_survives_login_redirect(): void
    {
        $user = User::factory()->create([
            'email' => 'michele@example.com',
            'password' => Hash::make('very-secure-password'),
        ]);
        $clientId = 'https://chatgpt.com/oauth/workout-memory/client.json';
        $redirectUri = 'https://chatgpt.com/connector/oauth/test-callback';

        $authorization = [
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => 'mcp:use',
            'state' => 'state-1',
            'code_challenge' => $this->pkceChallenge(str_repeat('a', 64)),
            'code_challenge_method' => 'S256',
            'resource' => 'https://workout-memory.test/mcp/workout-memory',
        ];
        $authorizationPath = '/oauth/authorize?'.http_build_query($authorization);
        $authorizationUrl = 'https://workout-memory.test'.$authorizationPath;

        $this->get($authorizationPath)
            ->assertRedirect(route('login'))
            ->assertSessionHas('url.intended');

        $this->assertSameAuthorizationUrl($authorizationUrl, (string) session('url.intended'));

        $loginRedirect = $this->post(route('login.store'), [
            'email' => 'michele@example.com',
            'password' => 'very-secure-password',
        ])->assertRedirect();

        $this->assertSameAuthorizationUrl($authorizationUrl, (string) $loginRedirect->headers->get('Location'));

        $this->assertAuthenticatedAs($user);

        $redirect = $this->get($authorizationPath)
            ->assertRedirect();

        $location = (string) $redirect->headers->get('Location');
        $this->assertStringStartsWith($redirectUri, $location);

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
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

    public function test_dynamic_client_registration_is_supported(): void
    {
        $this->postJson('/oauth/register', [
            'client_name' => 'ChatGPT',
            'redirect_uris' => ['https://chatgpt.com/connector/oauth/registered-callback'],
        ])
            ->assertCreated()
            ->assertJsonPath('token_endpoint_auth_method', 'none')
            ->assertJsonPath('scope', 'mcp:use')
            ->assertJsonStructure(['client_id', 'redirect_uris']);
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
