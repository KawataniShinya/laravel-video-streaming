<?php

namespace Tests\Feature\Videos;

use App\Enums\Role;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoView;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VideoPlaybackTest extends TestCase
{
    use RefreshDatabase;

    public function test_mp4_watch_renders_player_and_creates_view_record(): void
    {
        $user = User::factory()->create();
        $user->allowedPaths()->create(['path' => 'movies']);

        $this->makeVideoFile('movies/main.mp4', 'main');

        $response = $this
            ->actingAs($user)
            ->get(route('videos.watch', ['path' => 'movies/main.mp4'], false));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Videos/WatchMp4')
            ->where('filename', 'main.mp4')
            ->where('path', 'movies/main.mp4')
            ->where('lastPosition', 0)
        );

        $video = Video::where('path', 'movies/main.mp4')->firstOrFail();

        $this->assertDatabaseHas('video_views', [
            'user_id' => $user->id,
            'video_id' => $video->id,
        ]);
    }

    public function test_hls_watch_uses_existing_cache_and_renders_hls_player(): void
    {
        $user = User::factory()->create();
        $user->allowedPaths()->create(['path' => 'movies']);

        $path = 'movies/archive.avi';
        $hash = md5($path);

        $this->makeVideoFile($path, 'archive');
        $this->makeHlsCache($hash);

        $response = $this
            ->actingAs($user)
            ->get(route('videos.watch', ['path' => $path], false));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Videos/WatchHls')
            ->where('filename', 'archive.avi')
            ->where('path', $path)
            ->where('hash', $hash)
        );
    }

    public function test_secondary_vob_entry_redirects_to_primary_file(): void
    {
        $user = User::factory()->create();
        $user->allowedPaths()->create(['path' => 'dvd']);

        $this->makeVideoFile('dvd/VTS_01_1.VOB', 'main');
        $this->makeVideoFile('dvd/VTS_01_2.VOB', 'second');

        $response = $this
            ->actingAs($user)
            ->get(route('videos.watch', ['path' => 'dvd/VTS_01_2.VOB'], false));

        $response->assertRedirect(route('videos.watch', ['path' => 'dvd/VTS_01_1.VOB'], false));
    }

    public function test_history_only_shows_items_user_can_still_access(): void
    {
        $user = User::factory()->create();
        $user->allowedPaths()->create(['path' => 'movies']);

        $allowedVideo = Video::create(['path' => 'movies/main.mp4', 'hash' => md5('movies/main.mp4'), 'type' => 'file']);
        $hiddenVideo = Video::create(['path' => 'private/secret.mp4', 'hash' => md5('private/secret.mp4'), 'type' => 'file']);

        VideoView::create(['user_id' => $user->id, 'video_id' => $allowedVideo->id, 'last_position' => 12]);
        VideoView::create(['user_id' => $user->id, 'video_id' => $hiddenVideo->id, 'last_position' => 25]);

        $response = $this
            ->actingAs($user)
            ->get(route('videos.history', absolute: false));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Videos/History')
            ->has('items', 1)
            ->where('items.0.path', 'movies/main.mp4')
            ->where('items.0.last_position', 12)
        );
    }

    public function test_update_progress_persists_last_position(): void
    {
        $user = User::factory()->create();
        $user->allowedPaths()->create(['path' => 'movies']);

        $video = Video::create(['path' => 'movies/main.mp4', 'hash' => md5('movies/main.mp4'), 'type' => 'file']);

        $response = $this
            ->actingAs($user)
            ->postJson(route('videos.progress', absolute: false), [
                'path' => 'movies/main.mp4',
                'time' => 135,
            ]);

        $response->assertOk()->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('video_views', [
            'user_id' => $user->id,
            'video_id' => $video->id,
            'last_position' => 135,
        ]);
    }

    public function test_toggle_watch_status_adds_and_removes_view_record(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->post(route('videos.watched.toggle', absolute: false), ['path' => 'movies/main.mp4'])
            ->assertRedirect();

        $video = Video::where('path', 'movies/main.mp4')->firstOrFail();

        $this->assertDatabaseHas('video_views', [
            'user_id' => $user->id,
            'video_id' => $video->id,
        ]);

        $this
            ->actingAs($user)
            ->post(route('videos.watched.toggle', absolute: false), ['path' => 'movies/main.mp4'])
            ->assertRedirect();

        $this->assertDatabaseMissing('video_views', [
            'user_id' => $user->id,
            'video_id' => $video->id,
        ]);
    }

    public function test_admin_can_delete_existing_hls_cache_from_video_list(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin->value]);
        $video = Video::create(['path' => 'movies/archive.avi', 'hash' => md5('movies/archive.avi'), 'type' => 'file']);

        $this->makeHlsCache($video->hash, ['index.m3u8' => '#EXTM3U', 'segment.ts' => 'segment']);

        $response = $this
            ->actingAs($admin)
            ->post(route('videos.cache.delete', absolute: false), ['path' => $video->path]);

        $response->assertRedirect(route('videos.index', ['path' => 'movies'], false));
        $this->assertDirectoryDoesNotExist($this->hlsTestRoot . '/' . $video->hash);
    }
}
