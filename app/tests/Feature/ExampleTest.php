<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login_from_root(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login', absolute: false));
    }

    public function test_authenticated_users_are_redirected_to_dashboard_from_root(): void
    {
        $response = $this
            ->actingAs(User::factory()->create())
            ->get('/');

        $response->assertRedirect(route('dashboard', absolute: false));
    }
}
