<?php

namespace App\UseCase;

use App\DTOs\Favorite\FavoriteItemDTO;
use App\DTOs\Favorite\FavoriteListDTO;
use App\Models\User;
use App\Repositories\Interfaces\FavoriteRepositoryInterface;
use App\Repositories\Interfaces\VideoRepositoryInterface;
use App\Repositories\Interfaces\VideoViewRepositoryInterface;
use App\Services\Video\VideoCacheService;

class FavoriteUseCase
{
    public function __construct(
        private readonly FavoriteRepositoryInterface $favoriteRepository,
        private readonly VideoRepositoryInterface $videoRepository,
        private readonly VideoViewRepositoryInterface $videoViewRepository,
        private readonly VideoCacheService $cacheService,
    ) {
    }

    public function list(User $user): FavoriteListDTO
    {
        $favorites = $this->favoriteRepository->favoritesForUser($user);
        $items = [];

        foreach ($favorites as $fav) {
            $video = $fav->video;
            if (!$video) {
                continue;
            }

            $ext = strtolower(pathinfo($video->path, PATHINFO_EXTENSION));
            $isCached = $video->type === 'file' && in_array($ext, ['m2ts', 'avi', 'flv', 'vob'], true)
                ? $this->cacheService->isCached($video->hash)
                : false;

            $view = $this->videoViewRepository->findForUserAndVideo($user, $video);

            $items[] = new FavoriteItemDTO(
                id: $video->id,
                type: $video->type,
                name: basename($video->path),
                path: $video->path,
                ext: $ext,
                isCached: $isCached,
                isWatched: $view !== null,
                lastPosition: $view ? (int) $view->last_position : 0,
            );
        }

        return new FavoriteListDTO($items);
    }

    public function toggle(User $user, string $path, string $type): bool
    {
        $video = $this->videoRepository->firstOrCreateByPath(rawurldecode($path), $type);

        return $this->favoriteRepository->toggle($user, $video, $type);
    }
}
