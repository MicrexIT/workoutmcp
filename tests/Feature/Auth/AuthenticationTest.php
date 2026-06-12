<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
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
        $this->post(route('register.store'), [
            'name' => 'Michele',
            'email' => 'michele@example.com',
            'password' => 'very-secure-password',
            'password_confirmation' => 'very-secure-password',
        ])
            ->assertRedirect(route('home'))
            ->assertSessionHas('status', 'Account created.');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'michele@example.com',
            'name' => 'Michele',
        ]);
        $this->assertDatabaseHas('user_profiles', [
            'preferred_weight_unit' => 'kg',
            'preferred_distance_unit' => 'm',
            'timezone' => 'Europe/Paris',
        ]);
    }

    public function test_first_account_registration_redirects_to_intended_oauth_request(): void
    {
        $intendedUrl = 'https://workout-memory.test/oauth/authorize?response_type=code&client_id=chatgpt';

        $this->withSession(['url.intended' => $intendedUrl])
            ->post(route('register.store'), [
                'name' => 'Michele',
                'email' => 'michele@example.com',
                'password' => 'very-secure-password',
                'password_confirmation' => 'very-secure-password',
            ])
            ->assertRedirect($intendedUrl)
            ->assertSessionHas('status', 'Account created.');

        $this->assertAuthenticated();
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
