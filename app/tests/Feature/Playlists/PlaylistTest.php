<?php

namespace Tests\Feature\Playlists;

use App\Models\Playlist;
use App\Models\User;
use App\Models\VideoView;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlaylistTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $user = User::factory()->create();
        $user->allowedPaths()->create(['path' => 'movies']);
        $this->actingAs($user);

        return $user;
    }

    private function video(string $path = 'movies/one.mp4')
    {
        $this->makeVideoFile($path);

        return $this->getOrCreateVideo($path, 'file');
    }

    public function test_create_reorder_remove_and_delete_preserves_video_history(): void
    {
        $user = $this->owner();
        $one = $this->video();
        $two = $this->video('movies/two.mp4');
        VideoView::create(['user_id' => $user->id, 'video_id' => $one->id, 'last_position' => 12]);
        $this->post(route('playlists.store'), ['name' => 'My list', 'items' => [['video_id' => $one->id], ['video_id' => $two->id]]])->assertRedirect(route('playlists.index'));
        $list = Playlist::firstOrFail();
        $this->assertSame([$one->id, $two->id], $list->items->pluck('video_id')->all());
        [$first, $second] = $list->items->all();
        $this->put(route('playlists.update', $list), ['name' => 'Updated', 'items' => [['id' => $second->id], ['id' => $first->id]]])->assertRedirect();
        $this->assertSame([$two->id, $one->id], $list->fresh()->items->pluck('video_id')->all());
        $this->put(route('playlists.update', $list), ['name' => 'Updated', 'items' => [['id' => $first->id]]])->assertRedirect();
        $this->assertSame([$one->id], $list->fresh()->items->pluck('video_id')->all());
        $this->delete(route('playlists.destroy', $list))->assertRedirect();
        $this->assertDatabaseCount('playlist_items', 0);
        $this->assertDatabaseHas('video_views', ['video_id' => $one->id, 'last_position' => 12]);
        $this->assertDatabaseHas('videos', ['id' => $one->id]);
    }

    public function test_validation_and_permissions_apply_to_new_items(): void
    {
        $this->owner();
        $video = $this->video();
        $hidden = $this->video('private/secret.mp4');
        foreach ([[$video->id, $video->id], [$hidden->id], [99999]] as $ids) {
            $this->post(route('playlists.store'), ['name' => 'List', 'items' => array_map(fn ($id) => ['video_id' => $id], $ids)])->assertSessionHasErrors('items');
        }
        $this->post(route('playlists.store'), ['name' => '', 'items' => []])->assertSessionHasErrors('name');
        $this->assertDatabaseCount('playlists', 0);
        $this->post(route('playlists.store'), ['name' => 'Empty', 'items' => []])->assertRedirect();
        $this->post(route('playlists.store'), ['name' => 'Empty', 'items' => []])->assertRedirect();
        $this->assertDatabaseCount('playlists', 2);
    }

    public function test_other_users_cannot_read_edit_delete_or_play_lists_even_as_admin(): void
    {
        $owner = $this->owner();
        $video = $this->video();
        $list = Playlist::create(['name' => 'Private', 'user_id' => $owner->id]);
        $item = $list->items()->create(['video_id' => $video->id, 'position' => 0]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get(route('playlists.index'))->assertInertia(fn (Assert $p) => $p->has('playlists', 0));
        foreach (['edit' => ['playlist' => $list->id], 'watch' => ['playlist' => $list->id, 'item' => $item->id], 'next' => ['playlist' => $list->id, 'item' => $item->id]] as $action => $params) {
            $this->get(route('playlists.'.$action, $params))->assertNotFound();
        }
        $this->put(route('playlists.update', $list), ['name' => 'Stolen', 'items' => []])->assertNotFound();
        $this->delete(route('playlists.destroy', $list))->assertNotFound();
    }

    public function test_manual_resume_next_from_zero_shared_watched_and_no_loop(): void
    {
        $user = $this->owner();
        $one = $this->video();
        $two = $this->video('movies/two.mp4');
        $list = Playlist::create(['name' => 'List', 'user_id' => $user->id]);
        $first = $list->items()->create(['video_id' => $one->id, 'position' => 0]);
        $last = $list->items()->create(['video_id' => $two->id, 'position' => 1]);
        VideoView::create(['user_id' => $user->id, 'video_id' => $two->id, 'last_position' => 31]);
        $this->get(route('playlists.watch', [$list->id, $first->id]))->assertInertia(fn (Assert $p) => $p->component('Playlists/Watch')->where('playlist.items.0.is_watched', true)->where('hasNext', true));
        $this->get(route('playlists.watch', [$list->id, $last->id]))->assertInertia(fn (Assert $p) => $p->where('lastPosition', 31)->where('hasNext', false));
        $next = route('playlists.watch', ['playlist' => $list->id, 'item' => $last->id, 'start' => 1]);
        $this->get(route('playlists.next', [$list->id, $first->id]))->assertRedirect($next);
        $this->get($next)->assertInertia(fn (Assert $p) => $p->where('lastPosition', 0));
        $this->get(route('playlists.next', [$list->id, $last->id]))->assertRedirect(route('playlists.index'));
        $this->get(route('videos.watch', ['path' => $one->path]))->assertInertia(fn (Assert $p) => $p->component('Videos/WatchMp4'));
    }

    public function test_unavailable_entries_are_retained_redacted_and_skipped(): void
    {
        $user = $this->owner();
        $one = $this->video();
        $hidden = $this->video('private/secret.mp4');
        $missing = $this->getOrCreateVideo('movies/missing.mp4', 'file');
        $last = $this->video('movies/last.mp4');
        $list = Playlist::create(['name' => 'List', 'user_id' => $user->id]);
        foreach ([$one, $hidden, $missing, $last] as $position => $video) {
            $list->items()->create(['video_id' => $video->id, 'position' => $position]);
        }
        $entries = $list->items;
        $this->get(route('playlists.index'))->assertInertia(fn (Assert $p) => $p->where('playlists.0.items.1.name', 'Unavailable')->where('playlists.0.items.1.path', null)->where('playlists.0.items.2.playable', false));
        $this->get(route('playlists.watch', [$list->id, $entries[1]->id]))->assertRedirect(route('playlists.index'));
        $this->get(route('playlists.next', [$list->id, $entries[0]->id]))->assertRedirect(route('playlists.watch', ['playlist' => $list->id, 'item' => $entries[3]->id, 'start' => 1]));
        $this->put(route('playlists.update', $list), ['name' => 'Retained', 'items' => $entries->map(fn ($i) => ['id' => $i->id])->all()])->assertSessionHasNoErrors();
        $missing->delete();
        $this->get(route('playlists.index'))->assertInertia(fn (Assert $p) => $p->has('playlists.0.items', 4)->where('playlists.0.items.2.playable', false));
    }

    public function test_editor_filters_permissions_and_legacy_is_excluded(): void
    {
        $this->owner();
        $this->video();
        $this->video('private/secret.mp4');
        $this->get(route('playlists.create', ['path' => 'movies']))->assertInertia(fn (Assert $p) => $p->component('Playlists/Edit')->has('items', 1));
        $this->get(route('playlists.create', ['path' => 'private']))->assertForbidden();
        $this->withCookie('ui_mode', 'legacy')->get(route('playlists.index'))->assertRedirect('/dashboard');
    }

    public function test_next_rechecks_order_and_deleted_current_item(): void
    {
        $user = $this->owner();
        $list = Playlist::create(['name' => 'List', 'user_id' => $user->id]);
        $first = $list->items()->create(['video_id' => $this->video()->id, 'position' => 0]);
        $last = $list->items()->create(['video_id' => $this->video('movies/two.mp4')->id, 'position' => 1]);
        $last->update(['position' => 0]);
        $first->update(['position' => 1]);
        $this->get(route('playlists.next', [$list->id, $first->id]))->assertRedirect(route('playlists.index'));
        $first->delete();
        $this->get(route('playlists.next', [$list->id, $first->id]))->assertRedirect(route('playlists.index'));
    }

    public function test_item_ids_from_another_playlist_and_duplicate_deleted_items_are_rejected(): void
    {
        $user = $this->owner();
        $list = Playlist::create(['name' => 'List', 'user_id' => $user->id]);
        $other = Playlist::create(['name' => 'Other', 'user_id' => $user->id]);
        $video = $this->video();
        $item = $other->items()->create(['video_id' => $video->id, 'position' => 0]);
        $this->put(route('playlists.update', $list), ['name' => 'List', 'items' => [['id' => $item->id]]])->assertSessionHasErrors('items');
        $this->get(route('playlists.watch', [$list->id, $item->id]))->assertNotFound();
        $video->delete();
        $this->put(route('playlists.update', $other), ['name' => 'Other', 'items' => [['id' => $item->id], ['id' => $item->id]]])->assertSessionHasErrors('items');
    }

    public function test_admin_can_create_and_folder_or_secondary_vob_cannot_be_added(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $main = $this->video('dvd/VTS_01_1.VOB');
        $secondary = $this->video('dvd/VTS_01_2.VOB');
        $folder = $this->getOrCreateVideo('dvd', 'folder');
        foreach ([$secondary, $folder] as $invalid) {
            $this->post(route('playlists.store'), ['name' => 'DVD', 'items' => [['video_id' => $invalid->id]]])->assertSessionHasErrors('items');
        }
        $this->post(route('playlists.store'), ['name' => 'DVD', 'items' => [['video_id' => $main->id]]])->assertSessionHasNoErrors();
        $this->get(route('playlists.create', ['path' => 'dvd']))->assertInertia(fn (Assert $p) => $p->has('items', 1)->where('items.0.name', 'VTS_01_1.VOB'));
    }
}
