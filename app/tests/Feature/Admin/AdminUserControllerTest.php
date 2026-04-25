<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminUserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_users_cannot_access_user_management(): void
    {
        $user = User::factory()->create(['role' => Role::User->value]);

        $this->actingAs($user)->get(route('admin.users.index', absolute: false))->assertForbidden();
    }

    public function test_admin_can_create_and_update_a_user(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin->value]);
        $user = User::factory()->create(['role' => Role::User->value]);

        $this
            ->actingAs($admin)
            ->post(route('admin.users.store', absolute: false), [
                'name' => 'Created User',
                'email' => 'created@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'role' => Role::User->value,
            ])
            ->assertRedirect(route('admin.users.index', absolute: false));

        $created = User::where('email', 'created@example.com')->firstOrFail();
        $this->assertSame('Created User', $created->name);
        $this->assertSame(Role::User->value, $created->role);

        $this
            ->actingAs($admin)
            ->put(route('admin.users.update', $user, false), [
                'name' => 'Updated User',
                'email' => 'updated@example.com',
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
                'role' => Role::Admin->value,
            ])
            ->assertRedirect(route('admin.users.index', absolute: false));

        $user->refresh();

        $this->assertSame('Updated User', $user->name);
        $this->assertSame('updated@example.com', $user->email);
        $this->assertSame(Role::Admin->value, $user->role);
        $this->assertTrue(Hash::check('NewPassword123!', $user->password));
    }

    public function test_admin_cannot_delete_themselves(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin->value]);

        $this
            ->actingAs($admin)
            ->delete(route('admin.users.destroy', $admin, false))
            ->assertSessionHasErrors('error');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_allowed_paths_screen_lists_files_from_the_requested_directory(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin->value]);
        $user = User::factory()->create(['role' => Role::User->value]);

        $this->makeVideoDirectory('movies/series');
        $this->makeVideoFile('movies/main.mp4', 'main');

        $response = $this
            ->actingAs($admin)
            ->get(route('admin.users.allowed-paths.edit', ['user' => $user->id, 'path' => 'movies'], false));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Users/AllowedPaths')
            ->where('currentPath', 'movies')
            ->has('items', 2)
        );

        $items = collect($response->inertiaProps('items'))->keyBy('path');

        $this->assertSame('folder', $items['movies/series']['type']);
        $this->assertSame('file', $items['movies/main.mp4']['type']);
    }

    public function test_update_allowed_paths_removes_redundant_child_paths(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin->value]);
        $user = User::factory()->create(['role' => Role::User->value]);

        $this
            ->actingAs($admin)
            ->post(route('admin.users.allowed-paths.update', $user, false), [
                'paths' => ['movies', 'movies/series', 'movies/series/season1', 'docs'],
            ])
            ->assertRedirect(route('admin.users.edit', $user, false));

        $this->assertSame(
            ['docs', 'movies'],
            $user->fresh()->allowedPaths()->pluck('path')->sort()->values()->all()
        );
    }
}
