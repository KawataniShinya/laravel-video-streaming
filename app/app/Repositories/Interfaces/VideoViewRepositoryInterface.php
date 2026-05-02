<?php

namespace App\Repositories\Interfaces;

use App\Models\Video;
use App\Models\VideoView;
use App\Models\User;
use Illuminate\Support\Collection;

interface VideoViewRepositoryInterface
{
    public function firstOrCreateForUserAndVideo(User $user, Video $video): VideoView;

    public function updateProgress(User $user, Video $video, int $seconds): void;

    public function toggleWatched(User $user, Video $video): void;

    public function historyForUser(User $user): Collection;

    public function findForUserAndVideo(User $user, Video $video): ?VideoView;
}
