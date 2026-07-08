<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\WorkoutMemory\CurrentUserResolver;
use App\Services\WorkoutMemory\TrainingSummaryService;
use App\Services\WorkoutMemory\WorkoutLogger;
use App\Services\WorkoutMemory\WorkoutSessionManager;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Mockery;
use Tests\TestCase;

class WorkoutMemoryActivityLoggingTest extends TestCase
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
    }

    public function test_authentication_activity_is_logged_without_sensitive_fields(): void
    {
        Notification::fake();
        Log::spy();

        $this->post(route('register.store'), [
            'name' => 'Michele',
            'email' => 'michele@example.com',
            'password' => 'very-secure-password',
            'password_confirmation' => 'very-secure-password',
        ])->assertRedirect(route('verification.notice'));

        $user = User::query()->where('email', 'michele@example.com')->firstOrFail();

        Notification::assertSentTo($user, VerifyEmail::class);
        Log::shouldHaveReceived('info')->with('workout_memory.auth.registration.completed', Mockery::on(
            fn (array $context): bool => $context['user_id'] === $user->id
                && isset($context['email_hash'])
                && ($context['was_first_account'] ?? false) === true
                && ! array_key_exists('password', $context),
        ));

        $this->post(route('logout'))->assertRedirect(route('login'));

        Log::shouldHaveReceived('info')->with('workout_memory.auth.logout.succeeded', Mockery::on(
            fn (array $context): bool => $context['user_id'] === $user->id,
        ));

        $this->post(route('login.store'), [
            'email' => 'michele@example.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        Log::shouldHaveReceived('warning')->with('workout_memory.auth.login.failed', Mockery::on(
            fn (array $context): bool => isset($context['email_hash'])
                && ! in_array('michele@example.com', $context, true)
                && ! array_key_exists('password', $context),
        ));

        $user->forceFill(['email_verified_at' => now()])->save();

        $this->post(route('login.store'), [
            'email' => 'michele@example.com',
            'password' => 'very-secure-password',
        ])->assertRedirect(route('home'));

        Log::shouldHaveReceived('info')->with('workout_memory.auth.login.succeeded', Mockery::on(
            fn (array $context): bool => $context['user_id'] === $user->id
                && isset($context['user_email_hash'])
                && ! array_key_exists('password', $context),
        ));
    }

    public function test_oauth_authorization_activity_is_logged_without_codes_or_tokens(): void
    {
        $user = app(CurrentUserResolver::class)->user();
        $user->markEmailAsVerified();
        $verifier = str_repeat('a', 64);

        Log::spy();

        $clientId = (string) $this->postJson('/oauth/register', [
            'client_name' => 'ChatGPT',
            'redirect_uris' => ['https://chatgpt.com/connector/oauth/test-callback'],
        ])->assertCreated()->json('client_id');

        $authorization = $this->authorizationParams([
            'client_id' => $clientId,
            'code_challenge' => $this->pkceChallenge($verifier),
        ]);

        $this->actingAs($user)
            ->get('/oauth/authorize?'.http_build_query($authorization))
            ->assertOk()
            ->assertViewIs('oauth.authorize');

        $redirect = $this->actingAs($user)
            ->post(route('workout-memory.oauth.authorize.decision'), [...$authorization, 'action' => 'approve'])
            ->assertRedirect();

        $query = $this->redirectQuery($redirect, $authorization['redirect_uri']);

        $this->withHeader('Accept', 'application/json')->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'redirect_uri' => $authorization['redirect_uri'],
            'code' => $query['code'],
            'code_verifier' => $verifier,
        ])->assertOk();

        Log::shouldHaveReceived('info')->with('workout_memory.oauth.client.registered', Mockery::on(
            fn (array $context): bool => ($context['client_name'] ?? null) === 'ChatGPT'
                && ($context['redirect_uri_count'] ?? null) === 1
                && isset($context['client_id_hash'])
                && ! array_key_exists('client_id', $context),
        ));
        Log::shouldHaveReceived('info')->with('workout_memory.oauth.authorization.consent_shown', Mockery::on(
            fn (array $context): bool => $context['user_id'] === $user->id
                && isset($context['client_id_hash'])
                && ($context['has_state'] ?? false) === true,
        ));
        Log::shouldHaveReceived('info')->with('workout_memory.oauth.authorization.approved', Mockery::type('array'));
        Log::shouldHaveReceived('info')->with('workout_memory.oauth.authorization_code.issued', Mockery::on(
            fn (array $context): bool => ($context['approval_source'] ?? null) === 'fresh_approval'
                && ! array_key_exists('code', $context),
        ));
        Log::shouldHaveReceived('info')->with('workout_memory.oauth.token.issued', Mockery::on(
            fn (array $context): bool => ($context['grant_type'] ?? null) === 'authorization_code'
                && ! array_key_exists('access_token', $context)
                && ! array_key_exists('refresh_token', $context),
        ));
    }

    public function test_mcp_oauth_token_rejection_is_logged(): void
    {
        Log::spy();

        $this->postJson('/mcp/workout-memory', [])->assertUnauthorized();

        Log::shouldHaveReceived('warning')->with('workout_memory.mcp.oauth_token.rejected', Mockery::on(
            fn (array $context): bool => ($context['has_bearer_token'] ?? true) === false
                && ($context['resource_path'] ?? null) === 'mcp/workout-memory',
        ));
    }

    public function test_completed_workout_logging_and_retrieval_are_logged_without_raw_workout_text(): void
    {
        $this->seed();
        $user = app(CurrentUserResolver::class)->user();

        Log::spy();

        $logged = app(WorkoutLogger::class)->log($user, [
            'name' => 'Strength day',
            'idempotency_key' => 'log-message-1',
            'raw_input' => 'sensitive raw workout text',
            'exercises' => [[
                'raw_phrase' => 'back squats',
                'sets' => [[
                    'reps' => 5,
                    'load_value' => 100,
                    'load_unit' => 'kg',
                ]],
            ]],
        ]);

        $workoutId = (int) $logged['saved_session']['id'];

        app(TrainingSummaryService::class)->getWorkout($user, $workoutId);
        app(TrainingSummaryService::class)->paginatedRecentWorkouts($user, 10);
        app(TrainingSummaryService::class)->getWorkout($user, 9999);

        Log::shouldHaveReceived('info')->with('workout_memory.workout.log.created', Mockery::on(
            fn (array $context): bool => ($context['workout_id'] ?? null) === $workoutId
                && ($context['exercise_count'] ?? null) === 1
                && ($context['has_raw_input'] ?? false) === true
                && ! in_array('sensitive raw workout text', $context, true),
        ));
        Log::shouldHaveReceived('info')->with('workout_memory.workout.retrieve.loaded', Mockery::on(
            fn (array $context): bool => ($context['workout_id'] ?? null) === $workoutId,
        ));
        Log::shouldHaveReceived('info')->with('workout_memory.workouts.retrieve.listed', Mockery::on(
            fn (array $context): bool => ($context['result_count'] ?? null) === 1,
        ));
        Log::shouldHaveReceived('warning')->with('workout_memory.workout.retrieve.missing', Mockery::on(
            fn (array $context): bool => ($context['workout_id'] ?? null) === 9999,
        ));
    }

    public function test_live_session_logging_activity_is_logged(): void
    {
        $this->seed();
        $user = app(CurrentUserResolver::class)->user();
        $sessions = app(WorkoutSessionManager::class);

        Log::spy();

        $sessions->start($user, [
            'name' => 'Live legs',
            'idempotency_key' => 'live-start',
        ]);

        $sessions->appendExercise($user, [
            'idempotency_key' => 'live-append-leg-press',
            'exercise' => [
                'raw_phrase' => 'leg press',
                'sets' => [[
                    'reps' => 10,
                    'load_value' => 120,
                    'load_unit' => 'kg',
                ]],
            ],
        ]);

        $sessions->finish($user, [
            'idempotency_key' => 'live-finish',
            'perceived_effort' => 8,
        ]);

        Log::shouldHaveReceived('info')->with('workout_memory.workout.session.started', Mockery::on(
            fn (array $context): bool => ($context['session_was_created'] ?? false) === true
                && isset($context['workout_id']),
        ));
        Log::shouldHaveReceived('info')->with('workout_memory.workout.session.exercise_appended', Mockery::on(
            fn (array $context): bool => ($context['exercise_count'] ?? null) === 1
                && isset($context['appended_exercise_id']),
        ));
        Log::shouldHaveReceived('info')->with('workout_memory.workout.session.finished', Mockery::on(
            fn (array $context): bool => ($context['workout_status'] ?? null) === 'completed',
        ));
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
     * @return array<string, string>
     */
    private function redirectQuery(TestResponse $redirect, string $expectedRedirectUri): array
    {
        $location = (string) $redirect->headers->get('Location');
        $this->assertStringStartsWith($expectedRedirectUri, $location);

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        return $query;
    }

    private function pkceChallenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }
}
