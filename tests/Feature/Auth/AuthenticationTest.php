<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

    public function test_registration_closes_after_an_account_exists_by_default(): void
    {
        User::factory()->create();

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

    public function test_dashboard_routes_require_authentication(): void
    {
        User::factory()->create();

        $this->get('/')->assertRedirect(route('login'));
        $this->get('/exercises')->assertRedirect(route('login'));
        $this->get('/workouts')->assertRedirect(route('login'));
    }
}
