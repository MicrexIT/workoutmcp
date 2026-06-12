<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_public_landing_page(): void
    {
        $response = $this->get('/');

        $response->assertOk()->assertSee('Workout Memory');
    }
}
