<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HlsCacheControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_users_cannot_access_hls_cache_admin_pages(): void
    {
        $user = User::factory()->create(['role' => Role::User->value]);

        $this->actingAs($user)->get(route('admin.hls.index', absolute: false))->assertForbidden();
    }

    public function test_admin_index_lists_known_caches_and_statuses(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin->value]);
        $video = Video::create(['path' => 'movies/archive.avi', 'hash' => md5('movies/archive.avi'), 'type' => 'file']);

        $this->makeHlsCache($video->hash);
        $this->makeHlsCache('orphan-hash', ['ffmpeg.pid' => '999999']);

        $response = $this
            ->actingAs($admin)
            ->get(route('admin.hls.index', absolute: false));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/HlsCache/Index')
            ->has('caches', 2)
        );

        $caches = collect($response->inertiaProps('caches'))->keyBy('hash');

        $this->assertSame('movies/archive.avi', $caches[$video->hash]['path']);
        $this->assertSame('completed', $caches[$video->hash]['status']);
        $this->assertSame('Unknown (Source path not in database)', $caches['orphan-hash']['path']);
        $this->assertSame('failed', $caches['orphan-hash']['status']);
    }

    public function test_admin_can_fetch_cache_size_and_delete_multiple_caches(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin->value]);

        $this->makeHlsCache('hash-a', ['index.m3u8' => '#EXTM3U', 'segment.ts' => str_repeat('a', 128)]);
        $this->makeHlsCache('hash-b', ['index.m3u8' => '#EXTM3U']);

        $sizeResponse = $this
            ->actingAs($admin)
            ->get(route('admin.hls.size', ['hash' => 'hash-a'], false));

        $sizeResponse
            ->assertOk()
            ->assertJsonPath('hash', 'hash-a')
            ->assertJsonPath('size_bytes', fn ($value) => is_int($value) && $value > 0);

        $this
            ->actingAs($admin)
            ->post(route('admin.hls.destroy_multiple', absolute: false), [
                'hashes' => ['hash-a', 'hash-b'],
            ])
            ->assertRedirect(route('admin.hls.index', absolute: false));

        $this->assertDirectoryDoesNotExist($this->hlsTestRoot . '/hash-a');
        $this->assertDirectoryDoesNotExist($this->hlsTestRoot . '/hash-b');
    }
}
