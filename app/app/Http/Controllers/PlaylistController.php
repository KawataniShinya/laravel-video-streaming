<?php

namespace App\Http\Controllers;

use App\Models\Playlist;
use App\Services\Video\UserAccessService;
use App\UseCase\PlaylistUseCase;
use App\UseCase\VideoUseCase;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PlaylistController extends Controller
{
    public function __construct(private PlaylistUseCase $playlists, private VideoUseCase $videos, private UserAccessService $access) {}

    private function owned(Request $request, Playlist $playlist): void
    {
        abort_unless($playlist->user_id === $request->user()->id, 404);
    }

    public function index(Request $request)
    {
        return Inertia::render('Playlists/Index', ['status' => $request->session()->get('status'), 'playlists' => Playlist::where('user_id', $request->user()->id)
            ->latest('id')->get()->map(fn ($p) => $this->playlists->describe($request->user(), $p))]);
    }

    public function create(Request $request)
    {
        return $this->editor($request, new Playlist);
    }

    public function edit(Request $request, Playlist $playlist)
    {
        $this->owned($request, $playlist);

        return $this->editor($request, $playlist);
    }

    private function editor(Request $request, Playlist $playlist)
    {
        $path = $request->validate(['path' => 'nullable|string'])['path'] ?? '';
        $items = [];
        if ($this->access->canAccessPath($request->user(), $path)) {
            $items = $this->videos->list($request->user(), $path)->jsonSerialize()['items'];
            // VOB segments represent one concatenated title; only its primary entry is selectable.
            $items = array_values(array_filter($items, fn ($item) => $item->type === 'folder'
                || strtolower($item->ext) !== 'vob' || strtoupper($item->name) === 'VTS_01_1.VOB'));
        } else {
            abort_if($path !== '', 403);
        }

        return Inertia::render('Playlists/Edit', [
            'playlist' => $playlist->exists ? $this->playlists->describe($request->user(), $playlist) : null,
            'items' => $items, 'currentPath' => $path,
        ]);
    }

    private function data(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255', 'items' => 'present|array',
            'items.*' => 'array:id,video_id', 'items.*.id' => 'nullable|integer',
            'items.*.video_id' => 'nullable|integer',
        ]);
    }

    public function store(Request $request)
    {
        $this->playlists->save($request->user(), new Playlist, $this->data($request));

        return to_route('playlists.index');
    }

    public function update(Request $request, Playlist $playlist)
    {
        $this->owned($request, $playlist);
        $this->playlists->save($request->user(), $playlist, $this->data($request));

        return to_route('playlists.index');
    }

    public function destroy(Request $request, Playlist $playlist)
    {
        $this->owned($request, $playlist);
        $playlist->delete();

        return to_route('playlists.index');
    }

    public function watch(Request $request, Playlist $playlist, int $item)
    {
        $this->owned($request, $playlist);
        $entry = $playlist->items()->with('video')->findOrFail($item);
        if (! $this->playlists->playable($request->user(), $entry->video)) {
            return to_route('playlists.index')->with('status', 'This video is currently unavailable.');
        }
        $watch = $this->videos->watch($request->user(), $entry->video->path)->jsonSerialize();
        if ($request->boolean('start')) {
            $watch['lastPosition'] = 0;
        }
        $description = $this->playlists->describe($request->user(), $playlist);
        $after = false;
        $hasNext = false;
        foreach ($description['items'] as $row) {
            if ($after && $row['playable']) {
                $hasNext = true;
            }
            if ($row['id'] === $item) {
                $after = true;
            }
        }

        return Inertia::render('Playlists/Watch', $watch + [
            'playlist' => $description, 'currentItem' => $item, 'hasNext' => $hasNext,
        ]);
    }

    public function next(Request $request, Playlist $playlist, int $item)
    {
        $this->owned($request, $playlist);
        $current = $playlist->items()->find($item);
        if (! $current) {
            return to_route('playlists.index')->with('status', 'The current video was removed from this playlist.');
        }
        foreach ($playlist->items()->with('video')->where('position', '>', $current->position)->get() as $next) {
            if ($this->playlists->playable($request->user(), $next->video)) {
                return to_route('playlists.watch', ['playlist' => $playlist->id, 'item' => $next->id, 'start' => 1]);
            }
        }

        return to_route('playlists.index')->with('status', 'End of playlist.');
    }
}
