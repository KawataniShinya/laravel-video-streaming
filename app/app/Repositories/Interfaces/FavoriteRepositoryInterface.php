<?php

namespace App\Repositories\Interfaces;

use App\Models\Favorite;
use App\Models\User;
use App\Models\Video;
use Illuminate\Support\Collection;

interface FavoriteRepositoryInterface
{
    public function toggle(User $user, Video $video, string $type): bool;

    public function favoritesForUser(User $user): Collection;

    public function existsForUserAndVideo(User $user, Video $video): bool;
}
