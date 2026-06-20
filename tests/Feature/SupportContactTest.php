<?php

namespace Tests\Feature;

use App\Mail\SupportContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SupportContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_and_dashboard_link_to_contact_form(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Contact us')
            ->assertSee(route('support'), false);

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Need help?')
            ->assertSee(route('support', ['from' => 'dashboard']), false);
    }

    public function test_support_page_prefills_authenticated_user(): void
    {
        $user = User::factory()->create([
            'name' => 'Michele Rexha',
            'email' => 'michele@example.com',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('support', ['from' => 'dashboard']))
            ->assertOk()
            ->assertSee('Contact us')
            ->assertSee('value="Michele Rexha"', false)
            ->assertSee('value="michele@example.com"', false)
            ->assertSee('value="dashboard"', false);
    }

    public function test_guest_can_send_support_message(): void
    {
        Mail::fake();

        config(['workout_memory.support.email' => 'michele@remics.tech']);

        $this->from(route('support'))->post(route('support.store'), [
            'name' => 'Filip Forti',
            'email' => 'FILIP@example.com',
            'topic' => 'connection',
            'message' => 'I need help connecting the ChatGPT connector.',
            'context' => 'landing',
            'website' => '',
        ])
            ->assertRedirect(route('support'))
            ->assertSessionHas('status', 'Message sent. We will reply by email.');

        Mail::assertQueued(SupportContactMessage::class, function (SupportContactMessage $mail): bool {
            return $mail->hasTo('michele@remics.tech')
                && $mail->payload['name'] === 'Filip Forti'
                && $mail->payload['email'] === 'filip@example.com'
                && $mail->payload['topic'] === 'connection'
                && $mail->payload['context'] === 'landing';
        });
    }

    public function test_invalid_support_message_is_not_queued(): void
    {
        Mail::fake();

        $this->from(route('support'))->post(route('support.store'), [
            'name' => '',
            'email' => 'not-an-email',
            'topic' => 'unknown',
            'message' => 'short',
        ])
            ->assertRedirect(route('support'))
            ->assertSessionHasErrors(['name', 'email', 'topic', 'message']);

        Mail::assertNotQueued(SupportContactMessage::class);
    }
}
