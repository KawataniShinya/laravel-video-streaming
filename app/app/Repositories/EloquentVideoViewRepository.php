<?php

namespace App\Repositories;

use App\Models\User;
use App\Models\Video;
use App\Models\VideoView;
use App\Repositories\Interfaces\VideoViewRepositoryInterface;
use Illuminate\Support\Collection;

class EloquentVideoViewRepository implements VideoViewRepositoryInterface
{
    public function firstOrCreateForUserAndVideo(User $user, Video $video): VideoView
    {
        return VideoView::firstOrCreate([
            'user_id' => $user->id,
            'video_id' => $video->id,
        ]);
    }

    public function updateProgress(User $user, Video $video, int $seconds): void
    {
        VideoView::updateOrCreate(
            ['user_id' => $user->id, 'video_id' => $video->id],
            ['last_position' => $seconds]
        );
    }

    public function toggleWatched(User $user, Video $video): void
    {
        $view = VideoView::where('user_id', $user->id)->where('video_id', $video->id)->first();

        if ($view) {
            $view->delete();
            return;
        }

        VideoView::create([
            'user_id' => $user->id,
            'video_id' => $video->id,
        ]);
    }

    public function historyForUser(User $user): Collection
    {
        return VideoView::with('video')
            ->where('user_id', $user->id)
            ->orderBy('updated_at', 'desc')
            ->get();
    }

    public function findForUserAndVideo(User $user, Video $video): ?VideoView
    {
        return VideoView::where('user_id', $user->id)
            ->where('video_id', $video->id)
            ->first();
    }
}
