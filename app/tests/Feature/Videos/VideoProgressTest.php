<?php

namespace Tests\Feature\Videos;

use App\Models\Favorite;
use App\Models\Playlist;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoView;
use App\Services\Video\VideoProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VideoProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_progress_is_saved_and_shared_across_lists_for_the_current_user(): void
    {
        $user = User::factory()->create();
        $user->allowedPaths()->create(['path' => 'movies']);
        $this->makeVideoFile('movies/main.mp4');
        $video = Video::create(['path' => 'movies/main.mp4', 'hash' => md5('movies/main.mp4'), 'type' => 'file']);
        Favorite::create(['user_id' => $user->id, 'video_id' => $video->id, 'type' => 'file']);
        $playlist = Playlist::create(['user_id' => $user->id, 'name' => 'Progress']);
        $playlist->items()->create(['video_id' => $video->id, 'position' => 0]);
        $other = User::factory()->create();
        VideoView::create(['user_id' => $other->id, 'video_id' => $video->id, 'last_position' => 180]);

        $this->actingAs($user)->postJson(route('videos.progress'), [
            'path' => $video->path, 'time' => 50, 'duration' => 200,
        ])->assertOk()->assertJsonPath('progress_percent', 25);
        $this->assertEquals(200, $video->fresh()->duration);

        foreach ([route('videos.index', ['path' => 'movies']), route('videos.history'), route('favorites.index')] as $url) {
            $response = $this->get($url)->assertOk();
            $this->assertEquals(25, $response->inertiaProps('items.0.progress_percent'));
        }
        $response = $this->get(route('playlists.index'))->assertOk();
        $this->assertEquals(25, $response->inertiaProps('playlists.0.items.0.progress_percent'));

        // Older clients can still save a position without erasing the known length.
        $this->postJson(route('videos.progress'), ['path' => $video->path, 'time' => 60])->assertOk();
        $this->assertEquals(200, $video->fresh()->duration);
        $this->withHeader('User-Agent', 'Mozilla/5.0 (Linux; Android 4.4.2) AppleWebKit/537.36 Chrome/30.0.0.0 Safari/537.36');
        foreach ([route('videos.index', ['path' => 'movies']), route('videos.history'), route('favorites.index')] as $url) {
            $this->get($url)->assertOk()->assertSee('aria-valuenow="30"', false);
        }
    }

    public function test_hls_uses_completed_playback_timeline_instead_of_incorrect_source_or_saved_duration(): void
    {
        $user = User::factory()->create();
        $user->allowedPaths()->create(['path' => 'movies']);
        $this->makeVideoFile('movies/VTS_01_1.VOB');
        $video = Video::create(['path' => 'movies/VTS_01_1.VOB', 'hash' => md5('movies/VTS_01_1.VOB'), 'type' => 'file']);
        $video->duration = 13.014833;
        $video->save();
        $this->makeHlsCache($video->hash, [
            'total_duration.txt' => '13.014833',
            'pHigh.m3u8' => "#EXTM3U\n#EXTINF:1400.0,\ns0_0.ts\n#EXTINF:1455.819690,\ns0_1.ts\n#EXT-X-ENDLIST\n",
        ]);
        $this->actingAs($user)->postJson(route('videos.progress'), [
            'path' => $video->path, 'time' => 1111, 'duration' => 1111,
        ])->assertOk()->assertJsonPath('progress_percent', 38.9);
        $this->assertEquals(38.9, app(VideoProgressService::class)->percentage($video->fresh(), 1111));
        $this->assertEquals(38.9, $this->get(route('videos.history'))->inertiaProps('items.0.progress_percent'));
        $this->assertEquals(38.9, $this->get(route('videos.index', ['path' => 'movies']))->inertiaProps('items.0.progress_percent'));
    }

    public function test_partial_failed_or_missing_hls_cache_does_not_report_completion(): void
    {
        $video = new Video(['path' => 'movie.VOB', 'hash' => md5('movie.VOB'), 'type' => 'file']);
        $video->duration = 13;
        $service = app(VideoProgressService::class);
        $this->assertNull($service->percentage($video, 1111));
        $cache = $this->makeHlsCache($video->hash, [
            'total_duration.txt' => '13',
            'pHigh.m3u8' => "#EXTM3U\n#EXTINF:13.0,\ns0_0.ts\n",
        ]);
        $this->assertNull($service->percentage($video, 1111));
        file_put_contents($cache.'/pHigh.m3u8', "#EXT-X-ENDLIST\n", FILE_APPEND);
        file_put_contents($cache.'/transcoding.lock', '');
        $this->assertNull($service->percentage($video, 1111));
    }

    public function test_progress_response_tracks_finish_rewind_and_unknown_duration(): void
    {
        $user = User::factory()->create();
        $user->allowedPaths()->create(['path' => 'movies']);
        $video = Video::create(['path' => 'movies/main.mp4', 'hash' => md5('movies/main.mp4'), 'type' => 'file']);
        $this->actingAs($user)->postJson(route('videos.progress'), [
            'path' => $video->path, 'time' => 10,
        ])->assertOk()->assertJsonPath('progress_percent', null);
        $this->postJson(route('videos.progress'), [
            'path' => $video->path, 'time' => 200, 'duration' => 200.8,
        ])->assertOk()->assertJsonPath('progress_percent', 100);
        $this->postJson(route('videos.progress'), [
            'path' => $video->path, 'time' => 0,
        ])->assertOk()->assertJsonPath('progress_percent', 0);
    }

    public function test_unknown_duration_and_percentage_bounds(): void
    {
        $video = new Video(['path' => 'movie.mp4', 'hash' => md5('movie.mp4'), 'type' => 'file']);
        $service = app(VideoProgressService::class);
        $this->assertNull($service->percentage($video, 100));
        $video->duration = 200;
        $this->assertEquals(0, $service->percentage($video, 0));
        $this->assertEquals(0, $service->percentage($video, -10));
        $this->assertEquals(100, $service->percentage($video, 250));
        $this->assertEquals(99.5, $service->percentage($video, 199));
        $video->duration = 200.8;
        $this->assertEquals(100, $service->percentage($video, 200));
    }

    public function test_invalid_duration_is_rejected_without_saving_progress(): void
    {
        $user = User::factory()->create();
        $user->allowedPaths()->create(['path' => 'movies']);
        $video = Video::create(['path' => 'movies/main.mp4', 'hash' => md5('movies/main.mp4'), 'type' => 'file']);
        foreach ([0, -1, 'bad'] as $duration) {
            $this->actingAs($user)->postJson(route('videos.progress'), [
                'path' => $video->path, 'time' => 50, 'duration' => $duration,
            ])->assertUnprocessable();
        }
        $this->assertDatabaseCount('video_views', 0);
    }
}
