<?php

namespace Tests\Feature\Favorites;

use App\Models\Favorite;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoView;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FavoriteControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_toggle_creates_and_removes_favorite_records(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->post(route('favorites.toggle', absolute: false), [
                'path' => 'movies/main.mp4',
                'type' => 'file',
            ])
            ->assertRedirect();

        $video = Video::where('path', 'movies/main.mp4')->firstOrFail();

        $this->assertDatabaseHas('favorites', [
            'user_id' => $user->id,
            'video_id' => $video->id,
            'type' => 'file',
        ]);

        $this
            ->actingAs($user)
            ->post(route('favorites.toggle', absolute: false), [
                'path' => 'movies/main.mp4',
                'type' => 'file',
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('favorites', [
            'user_id' => $user->id,
            'video_id' => $video->id,
        ]);
    }

    public function test_index_returns_favorites_with_cached_and_watched_state(): void
    {
        $user = User::factory()->create();

        $folder = Video::create(['path' => 'movies/series', 'hash' => md5('movies/series'), 'type' => 'folder']);
        $file = Video::create(['path' => 'movies/archive.avi', 'hash' => md5('movies/archive.avi'), 'type' => 'file']);

        Favorite::create(['user_id' => $user->id, 'video_id' => $folder->id, 'type' => 'folder']);
        Favorite::create(['user_id' => $user->id, 'video_id' => $file->id, 'type' => 'file']);
        VideoView::create(['user_id' => $user->id, 'video_id' => $file->id, 'last_position' => 33]);

        $this->makeHlsCache($file->hash);

        $response = $this
            ->actingAs($user)
            ->get(route('favorites.index', absolute: false));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Favorites/Index')
            ->has('items', 2)
        );

        $items = collect($response->inertiaProps('items'))->keyBy('path');

        $this->assertTrue($items['movies/series']['is_favorited']);
        $this->assertSame('folder', $items['movies/series']['type']);
        $this->assertTrue($items['movies/archive.avi']['is_favorited']);
        $this->assertTrue($items['movies/archive.avi']['is_watched']);
        $this->assertTrue($items['movies/archive.avi']['is_cached']);
    }
}
