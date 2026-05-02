<?php

namespace Tests\Unit\Services\Video;

use App\Enums\Role;
use App\Models\User;
use App\Services\Video\UserAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAccessServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_access_parent_child_and_root_paths(): void
    {
        $user = User::factory()->create(['role' => Role::User->value]);
        $user->allowedPaths()->create(['path' => 'movies']);

        $service = app(UserAccessService::class);

        $this->assertTrue($service->canAccessPath($user, ''));
        $this->assertTrue($service->canAccessPath($user, 'movies'));
        $this->assertTrue($service->canAccessPath($user, 'movies/series/episode1.mp4'));
        $this->assertTrue($service->canAccessPath($user, 'movies/series'));
        $this->assertFalse($service->canAccessPath($user, 'docs'));
    }

    public function test_admin_can_access_any_path(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin->value]);

        $service = app(UserAccessService::class);

        $this->assertTrue($service->canAccessPath($admin, ''));
        $this->assertTrue($service->canAccessPath($admin, 'any/deep/path/file.mp4'));
    }

    public function test_prune_redundant_paths_removes_children_and_duplicates(): void
    {
        $service = app(UserAccessService::class);

        $this->assertSame(
            ['docs', 'movies'],
            $service->pruneRedundantPaths(['movies', 'movies/series', 'movies/series/season1', 'docs', 'movies'])
        );
    }
}
