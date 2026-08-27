<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Mail\Transport\ResendTransport;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_page_is_available_for_the_first_account(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('Create account');
    }

    public function test_first_account_can_register_and_is_authenticated(): void
    {
        Notification::fake();

        $this->post(route('register.store'), [
            'name' => 'Michele',
            'email' => 'michele@example.com',
            'password' => 'very-secure-password',
            'password_confirmation' => 'very-secure-password',
        ])
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('status', 'Account created. Check your email to verify your address.');

        $user = User::query()->where('email', 'michele@example.com')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertDatabaseHas('users', [
            'email' => 'michele@example.com',
            'name' => 'Michele',
        ]);
        $this->assertDatabaseHas('user_profiles', [
            'preferred_weight_unit' => 'kg',
            'preferred_distance_unit' => 'm',
            'timezone' => 'Europe/Paris',
        ]);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_registration_failure_rolls_back_the_account_and_returns_a_form_error(): void
    {
        Notification::fake();
        Event::listen(Registered::class, function (): never {
            throw new RuntimeException('The verification email could not be sent.');
        });

        $this->from(route('register'))
            ->post(route('register.store'), [
                'name' => 'Michele',
                'email' => 'michele@example.com',
                'password' => 'very-secure-password',
                'password_confirmation' => 'very-secure-password',
            ])
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors([
                'email' => 'We could not create your account. Please try again.',
            ])
            ->assertSessionHasInput('name', 'Michele')
            ->assertSessionHasInput('email', 'michele@example.com')
            ->assertSessionMissing('_old_input.password');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('user_profiles', 0);
    }

    public function test_first_account_registration_preserves_intended_oauth_request_until_verification(): void
    {
        Notification::fake();

        $intendedUrl = 'https://workout-memory.test/oauth/authorize?response_type=code&client_id=chatgpt';

        $this->withSession(['url.intended' => $intendedUrl])
            ->post(route('register.store'), [
                'name' => 'Michele',
                'email' => 'michele@example.com',
                'password' => 'very-secure-password',
                'password_confirmation' => 'very-secure-password',
            ])
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('status', 'Account created. Check your email to verify your address.');

        $user = User::query()->where('email', 'michele@example.com')->firstOrFail();
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1($user->email),
            ],
        );

        $this->assertAuthenticatedAs($user);

        $this->get($verificationUrl)
            ->assertRedirect($intendedUrl)
            ->assertSessionHas('status', 'Email verified.');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_registration_stays_open_after_an_account_exists_by_default(): void
    {
        User::factory()->create();

        $this->get('/register')
            ->assertOk()
            ->assertSee('Create account');
    }

    public function test_registration_can_be_closed_via_config(): void
    {
        User::factory()->create();

        config(['workout_memory.registration.enabled' => false]);

        $this->get('/register')
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Registration is closed.');

        $this->post(route('register.store'), [
            'name' => 'Another User',
            'email' => 'another@example.com',
            'password' => 'very-secure-password',
            'password_confirmation' => 'very-secure-password',
        ])->assertForbidden();
    }

    public function test_users_can_login_and_logout(): void
    {
        $user = User::factory()->create([
            'email' => 'michele@example.com',
            'password' => Hash::make('very-secure-password'),
        ]);

        $this->get('/login')
            ->assertOk()
            ->assertSee('Sign in');

        $this->post(route('login.store'), [
            'email' => 'michele@example.com',
            'password' => 'very-secure-password',
        ])->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($user);

        $this->post(route('logout'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'You have been signed out.');

        $this->assertGuest();
    }

    public function test_configured_public_oauth_url_does_not_override_local_login_action(): void
    {
        User::factory()->create();

        config()->set('workout_memory.oauth.public_url', 'https://public-workout.example');

        $this->bootUrlProviderFor('http://127.0.0.1:8000/login');

        $this->get('http://127.0.0.1:8000/login')
            ->assertOk()
            ->assertSee('action="http://127.0.0.1:8000/login"', false)
            ->assertDontSee('public-workout.example', false);
    }

    public function test_configured_public_oauth_url_does_not_override_logged_in_dashboard_links(): void
    {
        $this->actingAs(User::factory()->create());

        config()->set('workout_memory.oauth.public_url', 'https://public-workout.example');

        $this->bootUrlProviderFor('http://127.0.0.1:8000/dashboard');

        $this->get('http://127.0.0.1:8000/dashboard')
            ->assertOk()
            ->assertSee('href="http://127.0.0.1:8000/exercises"', false)
            ->assertSee('href="http://127.0.0.1:8000/workouts"', false)
            ->assertSee('action="http://127.0.0.1:8000/logout"', false)
            ->assertDontSee('public-workout.example', false);
    }

    public function test_configured_public_oauth_url_is_used_for_matching_public_host_requests(): void
    {
        config()->set('workout_memory.oauth.public_url', 'https://public-workout.example');

        $this->bootUrlProviderFor('http://public-workout.example/oauth/authorize');

        $this->assertSame('https://public-workout.example/login', route('login'));
    }

    public function test_dashboard_routes_require_authentication(): void
    {
        User::factory()->create();

        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->get('/exercises')->assertRedirect(route('login'));
        $this->get('/workouts')->assertRedirect(route('login'));
    }

    public function test_dashboard_routes_require_verified_email(): void
    {
        $this->actingAs(User::factory()->unverified()->create());

        $this->get('/dashboard')->assertRedirect(route('verification.notice'));
        $this->get('/exercises')->assertRedirect(route('verification.notice'));
        $this->get('/workouts')->assertRedirect(route('verification.notice'));
    }

    public function test_verification_notification_can_be_resent(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->post(route('verification.send'))
            ->assertRedirect()
            ->assertSessionHas('status', 'Verification link sent.');

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_resend_mailer_can_be_resolved_for_production_email_delivery(): void
    {
        config()->set('services.resend.key', 're_test_key');

        $this->assertInstanceOf(
            ResendTransport::class,
            Mail::mailer('resend')->getSymfonyTransport(),
        );
    }

    public function test_resend_configuration_accepts_the_legacy_key_alias(): void
    {
        $repository = Env::getRepository();
        $originalApiKey = Env::get('RESEND_API_KEY');
        $originalLegacyKey = Env::get('RESEND_KEY');

        try {
            $repository->clear('RESEND_API_KEY');
            $repository->set('RESEND_KEY', 're_legacy_key');

            $services = require config_path('services.php');

            $this->assertSame('re_legacy_key', $services['resend']['key']);
        } finally {
            $repository->clear('RESEND_API_KEY');
            $repository->clear('RESEND_KEY');

            if ($originalApiKey !== null) {
                $repository->set('RESEND_API_KEY', $originalApiKey);
            }

            if ($originalLegacyKey !== null) {
                $repository->set('RESEND_KEY', $originalLegacyKey);
            }
        }
    }

    public function test_login_route_is_rate_limited(): void
    {
        config()->set('workout_memory.rate_limits.login_per_minute', 1);
        config()->set('workout_memory.rate_limits.login_email_per_minute', 1);

        User::factory()->create([
            'email' => 'michele@example.com',
            'password' => Hash::make('very-secure-password'),
        ]);

        $this->post(route('login.store'), [
            'email' => 'michele@example.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->post(route('login.store'), [
            'email' => 'michele@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    private function bootUrlProviderFor(string $url): void
    {
        $request = Request::create($url);

        $this->app->instance('request', $request);

        URL::setRequest($request);
        URL::useOrigin(null);
        URL::forceScheme(null);

        (new AppServiceProvider($this->app))->boot();
    }
}
