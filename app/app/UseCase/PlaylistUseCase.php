<?php

namespace App\UseCase;

use App\Models\Playlist;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoView;
use App\Services\Video\VideoProgressService;
use App\Services\Video\UserAccessService;
use App\Services\Video\VideoPathService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlaylistUseCase
{
    public function __construct(private UserAccessService $access, private VideoPathService $paths, private VideoProgressService $progress) {}

    public function playable(User $user, ?Video $video): bool
    {
        if (! $video || $video->type !== 'file' || ! $this->access->canAccessPath($user, $video->path)) {
            return false;
        }
        $path = $this->paths->resolvePath($video->path);

        return $path && is_file($path)
            && in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['mp4', 'm2ts', 'avi', 'flv', 'vob'], true)
            && $this->paths->normalizePrimaryVobPath($video->path) === $video->path;
    }

    public function describe(User $user, Playlist $playlist): array
    {
        $playlist->load('items.video');
        $watched = VideoView::where('user_id', $user->id)
            ->whereIn('video_id', $playlist->items->pluck('video_id'))->get()->keyBy('video_id');

        return [
            'id' => $playlist->id, 'name' => $playlist->name,
            'items' => $playlist->items->map(function ($item) use ($user, $watched) {
                $video = $item->video;
                $visible = $video && $this->access->canAccessPath($user, $video->path);

                return [
                    'id' => $item->id, 'video_id' => $visible ? $video->id : null,
                    'name' => $visible ? basename($video->path) : 'Unavailable',
                    'path' => $visible ? $video->path : null,
                    'playable' => $this->playable($user, $video),
                    'progress_percent' => $visible ? $this->progress->percentage($video, (int) ($watched->get($video->id)?->last_position ?? 0)) : null,
                    'is_watched' => $visible && $watched->has($video->id),
                ];
            })->values()->all(),
        ];
    }

    public function save(User $user, Playlist $playlist, array $data): void
    {
        DB::transaction(function () use ($user, $playlist, $data) {
            if ($playlist->exists) {
                Playlist::whereKey($playlist->id)->lockForUpdate()->firstOrFail();
            }
            $existing = $playlist->exists ? $playlist->items()->get()->keyBy('id') : collect();
            $rows = [];
            $seen = [];
            $seenItems = [];
            foreach ($data['items'] as $input) {
                $item = isset($input['id']) ? $existing->get($input['id']) : null;
                if (isset($input['id']) && ! $item) {
                    throw ValidationException::withMessages(['items' => 'This playlist has changed. Reload the page.']);
                }
                if ($item && isset($seenItems[$item->id])) {
                    throw ValidationException::withMessages(['items' => 'This item is already in the playlist.']);
                }
                if ($item) {
                    $seenItems[$item->id] = true;
                }
                $video = $item ? $item->video : Video::find($input['video_id'] ?? 0);
                if (! $item && ! $this->playable($user, $video)) {
                    throw ValidationException::withMessages(['items' => 'Some videos cannot be added.']);
                }
                if ($video && isset($seen[$video->id])) {
                    throw ValidationException::withMessages(['items' => 'This video is already in the playlist.']);
                }
                if ($video) {
                    $seen[$video->id] = true;
                }
                $rows[] = [$item, $video];
            }
            $playlist->fill(['user_id' => $user->id, 'name' => $data['name']])->save();
            $keep = collect($rows)->map(fn ($row) => $row[0]?->id)->filter()->all();
            $playlist->items()->whereNotIn('id', $keep)->delete();
            foreach ($rows as $position => [$item, $video]) {
                if ($item) {
                    $item->update(['position' => $position]);
                } else {
                    $playlist->items()->create(['video_id' => $video->id, 'position' => $position]);
                }
            }
        });
    }
}
