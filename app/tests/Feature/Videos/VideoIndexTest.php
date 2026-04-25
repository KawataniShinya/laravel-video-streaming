<?php

namespace Tests\Feature\Videos;

use App\Models\Favorite;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoView;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VideoIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_only_accessible_items_and_attaches_statuses(): void
    {
        $user = User::factory()->create();
        $user->allowedPaths()->create(['path' => 'movies']);

        $this->makeVideoDirectory('movies/series');
        $this->makeVideoDirectory('private');
        $this->makeVideoFile('movies/main.mp4', 'main');
        $this->makeVideoFile('movies/archive.m2ts', 'archive');
        $this->makeVideoFile('movies/VTS_01_1.VOB', 'dvd-main');
        $this->makeVideoFile('movies/VTS_01_2.VOB', 'dvd-extra');
        $this->makeVideoFile('private/secret.mp4', 'secret');

        $mainVideo = Video::create(['path' => 'movies/main.mp4', 'hash' => md5('movies/main.mp4'), 'type' => 'file']);
        $archiveVideo = Video::create(['path' => 'movies/archive.m2ts', 'hash' => md5('movies/archive.m2ts'), 'type' => 'file']);
        $extraVobVideo = Video::create(['path' => 'movies/VTS_01_2.VOB', 'hash' => md5('movies/VTS_01_2.VOB'), 'type' => 'file']);

        Favorite::create(['user_id' => $user->id, 'video_id' => $mainVideo->id, 'type' => 'file']);
        VideoView::create(['user_id' => $user->id, 'video_id' => $mainVideo->id, 'last_position' => 42]);
        Favorite::create(['user_id' => $user->id, 'video_id' => $extraVobVideo->id, 'type' => 'file']);
        VideoView::create(['user_id' => $user->id, 'video_id' => $extraVobVideo->id, 'last_position' => 7]);

        $this->makeHlsCache($archiveVideo->hash);

        $response = $this
            ->actingAs($user)
            ->get(route('videos.index', ['path' => 'movies'], false));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Videos/Index')
            ->where('currentPath', 'movies')
            ->has('breadcrumbs', 1)
            ->where('breadcrumbs.0.path', 'movies')
        );

        $items = collect($response->inertiaProps('items'))->keyBy('path');

        $this->assertSame(
            ['movies/VTS_01_1.VOB', 'movies/VTS_01_2.VOB', 'movies/archive.m2ts', 'movies/main.mp4', 'movies/series'],
            $items->keys()->sort()->values()->all()
        );
        $this->assertTrue($items['movies/main.mp4']['is_watched']);
        $this->assertTrue($items['movies/main.mp4']['is_favorited']);
        $this->assertTrue($items['movies/archive.m2ts']['is_cached']);
        $this->assertFalse($items['movies/VTS_01_2.VOB']['is_watched']);
        $this->assertFalse($items['movies/VTS_01_2.VOB']['is_favorited']);
    }

    public function test_index_redirects_unauthorized_users_back_to_dashboard(): void
    {
        $user = User::factory()->create();
        $user->allowedPaths()->create(['path' => 'movies/allowed']);

        $this->makeVideoDirectory('movies/secret');

        $response = $this
            ->actingAs($user)
            ->get(route('videos.index', ['path' => 'movies/secret'], false));

        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_index_redirects_file_paths_to_watch_screen(): void
    {
        $user = User::factory()->create();
        $user->allowedPaths()->create(['path' => 'movies']);

        $this->makeVideoFile('movies/main.mp4', 'main');

        $response = $this
            ->actingAs($user)
            ->get(route('videos.index', ['path' => 'movies/main.mp4'], false));

        $response->assertRedirect(route('videos.watch', ['path' => 'movies/main.mp4'], false));
    }
}
