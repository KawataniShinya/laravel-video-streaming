<?php

namespace App\Repositories;

use App\Models\Favorite;
use App\Models\User;
use App\Models\Video;
use App\Repositories\Interfaces\FavoriteRepositoryInterface;
use Illuminate\Support\Collection;

class EloquentFavoriteRepository implements FavoriteRepositoryInterface
{
    public function toggle(User $user, Video $video, string $type): bool
    {
        $favorite = Favorite::where('user_id', $user->id)
            ->where('video_id', $video->id)
            ->first();

        if ($favorite) {
            $favorite->delete();

            return false;
        }

        Favorite::create([
            'user_id' => $user->id,
            'video_id' => $video->id,
            'type' => $type,
        ]);

        return true;
    }

    public function favoritesForUser(User $user): Collection
    {
        return Favorite::with('video')->where('user_id', $user->id)->get();
    }

    public function existsForUserAndVideo(User $user, Video $video): bool
    {
        return Favorite::where('user_id', $user->id)->where('video_id', $video->id)->exists();
    }
}
