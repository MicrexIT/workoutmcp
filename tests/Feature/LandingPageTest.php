<?php

namespace Tests\Feature;

use App\Services\WorkoutMemory\CurrentUserResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_landing_with_connector_details(): void
    {
        $mcpUrl = rtrim((string) config('workout_memory.oauth.public_url'), '/').'/mcp/workout-memory';

        $this->get('/')
            ->assertOk()
            ->assertSee('Workout Memory')
            ->assertSee($mcpUrl)
            ->assertSee('https://chatgpt.com/?q=', false)
            ->assertSee('https://claude.ai/new?q=', false)
            ->assertSee(route('login'), false)
            ->assertSee(route('docs'), false)
            ->assertSee(route('privacy'), false)
            ->assertSee(route('support'), false)
            ->assertSee('Remics Software Technologies - FZCO');
    }

    public function test_llms_txt_describes_the_mcp_server(): void
    {
        $publicUrl = rtrim((string) config('workout_memory.oauth.public_url'), '/');

        $this->get('/llms.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee($publicUrl.'/mcp/workout-memory', false)
            ->assertSee('Model Context Protocol', false);
    }

    public function test_public_review_pages_are_available(): void
    {
        $mcpUrl = rtrim((string) config('workout_memory.oauth.public_url'), '/').'/mcp/workout-memory';
        $supportEmail = (string) config('workout_memory.support.email');

        $this->get('/docs')
            ->assertOk()
            ->assertSee('Connector documentation')
            ->assertSee($mcpUrl, false)
            ->assertSee($supportEmail, false);

        $this->get('/privacy')
            ->assertOk()
            ->assertSee('Privacy Policy')
            ->assertSee('Last updated June 15, 2026')
            ->assertSee($supportEmail, false);

        $this->get('/support')
            ->assertOk()
            ->assertSee('Support')
            ->assertSee($mcpUrl, false)
            ->assertSee($supportEmail, false);
    }

    public function test_landing_shows_closed_notice_when_registration_is_closed(): void
    {
        config(['workout_memory.registration.enabled' => false]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Registration is closed at the moment')
            ->assertDontSee(route('register'), false);
    }

    public function test_landing_links_registration_when_open(): void
    {
        config(['workout_memory.registration.enabled' => true]);

        $this->get('/')
            ->assertOk()
            ->assertSee('create your free account')
            ->assertSee(route('register'), false);
    }

    public function test_authenticated_user_sees_dashboard_link_on_landing(): void
    {
        $this->seed();

        $this->actingAs(app(CurrentUserResolver::class)->user())
            ->get('/')
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee(route('home'), false);
    }

    public function test_guest_is_redirected_from_dashboard_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_open_dashboard(): void
    {
        $this->seed();

        $this->actingAs(app(CurrentUserResolver::class)->user())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Workout Memory MCP');
    }
}
