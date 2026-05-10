<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/login');
    }

    public function test_expired_session_redirects_back_to_login_with_status(): void
    {
        // Define a temporary route that explicitly returns a 419 response
        // to test our custom exception handler in bootstrap/app.php.
        \Illuminate\Support\Facades\Route::post('/test-419', function () {
            abort(419);
        });

        $this->from('/login');

        $response = $this->post('/test-419');

        // Our handler should convert the 419 into a 302 redirect back to /login
        $response->assertStatus(302);
        $response->assertRedirect('/login');
        $response->assertSessionHas('status', 'The page expired, please try again.');
    }
}
