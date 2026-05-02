<?php

namespace App\UseCase;

use App\DTOs\Video\HistoryItemDTO;
use App\DTOs\Video\HistoryListDTO;
use App\DTOs\Video\VideoItemDTO;
use App\DTOs\Video\VideoLibraryDTO;
use App\DTOs\Video\VideoWatchDTO;
use App\Models\User;
use App\Repositories\Interfaces\FavoriteRepositoryInterface;
use App\Repositories\Interfaces\VideoRepositoryInterface;
use App\Repositories\Interfaces\VideoViewRepositoryInterface;
use App\Services\Video\UserAccessService;
use App\Services\Video\VideoCacheService;
use App\Services\Video\VideoPathService;
use App\ValueObjects\Video\VideoPath;
use Illuminate\Support\Facades\File;

class VideoUseCase
{
    public function __construct(
        private readonly VideoRepositoryInterface $videoRepository,
        private readonly VideoViewRepositoryInterface $videoViewRepository,
        private readonly FavoriteRepositoryInterface $favoriteRepository,
        private readonly UserAccessService $accessService,
        private readonly VideoPathService $pathService,
        private readonly VideoCacheService $cacheService,
    ) {
    }

    public function list(User $user, ?string $path = null): VideoLibraryDTO
    {
        $fullPath = $this->pathService->resolvePath($path);
        if (!$fullPath || !File::isDirectory($fullPath)) {
            abort(404);
        }

        $items = [];

        foreach (File::directories($fullPath) as $dir) {
            $relativePath = ($path ? $path . '/' : '') . basename($dir);
            if (!$this->accessService->canAccessPath($user, $relativePath)) {
                continue;
            }

            $video = $this->videoRepository->firstOrCreateByPath($relativePath, 'folder');
            $items[] = new VideoItemDTO(
                id: $video->id,
                type: 'folder',
                name: basename($dir),
                path: $relativePath,
                ext: '',
                size: '',
                isCached: false,
                isWatched: false,
                isFavorited: false,
            );
        }

        foreach (File::files($fullPath) as $file) {
            $ext = strtolower($file->getExtension());
            if (!in_array($ext, ['mp4', 'm2ts', 'avi', 'flv', 'vob'], true)) {
                continue;
            }

            $relativePath = ($path ? $path . '/' : '') . $file->getFilename();
            if (!$this->accessService->canAccessPath($user, $relativePath)) {
                continue;
            }

            $video = $this->videoRepository->firstOrCreateByPath($relativePath, 'file');
            $isCached = in_array($ext, ['m2ts', 'avi', 'flv', 'vob'], true) && $this->cacheService->isCached($video->hash);

            $items[] = new VideoItemDTO(
                id: $video->id,
                type: 'file',
                name: $file->getFilename(),
                path: $relativePath,
                ext: $ext,
                size: $this->formatBytes($file->getSize()),
                isCached: $isCached,
                isWatched: $this->videoViewRepository->findForUserAndVideo($user, $video) !== null,
                isFavorited: $this->favoriteRepository->existsForUserAndVideo($user, $video),
            );
        }

        $itemIds = collect($items)->pluck('id');
        $watchedVideoIds = $this->videoViewRepository->historyForUser($user)
            ->whereIn('video_id', $itemIds)
            ->pluck('video_id')
            ->flip()
            ->toArray();
        $favoriteVideoIds = $this->favoriteRepository->favoritesForUser($user)
            ->whereIn('video_id', $itemIds)
            ->pluck('video_id')
            ->flip()
            ->toArray();

        foreach ($items as $index => $item) {
            $isWatched = isset($watchedVideoIds[$item->id]);
            $isFavorited = isset($favoriteVideoIds[$item->id]);

            if ($item->type === 'file' && $item->ext === 'vob' && strtoupper(basename($item->path)) !== 'VTS_01_1.VOB') {
                $isWatched = false;
                $isFavorited = false;
            }

            $items[$index] = new VideoItemDTO(
                id: $item->id,
                type: $item->type,
                name: $item->name,
                path: $item->path,
                ext: $item->ext,
                size: $item->size,
                isCached: $item->isCached,
                isWatched: $isWatched,
                isFavorited: $isFavorited,
            );
        }

        return new VideoLibraryDTO(
            items: $items,
            currentPath: $path,
            breadcrumbs: $this->pathService->breadcrumbs($path),
        );
    }

    public function history(User $user): HistoryListDTO
    {
        $views = $this->videoViewRepository->historyForUser($user);
        $items = [];

        foreach ($views as $view) {
            $video = $view->video;
            if (!$video || !$this->accessService->canAccessPath($user, $video->path)) {
                continue;
            }

            $items[] = new HistoryItemDTO(
                id: $video->id,
                name: basename($video->path),
                path: $video->path,
                lastPosition: (int) $view->last_position,
                updatedAt: $view->updated_at->format('Y-m-d H:i'),
                updatedAtHuman: $view->updated_at->diffForHumans(),
            );
        }

        return new HistoryListDTO($items);
    }

    public function watch(User $user, string $path): VideoWatchDTO
    {
        $path = rawurldecode($path);
        $fullPath = $this->pathService->resolvePath($path);

        if (!$fullPath || !File::isFile($fullPath)) {
            abort(404);
        }

        $videoPath = new VideoPath($path);
        $filename = basename($fullPath);
        $video = $this->videoRepository->firstOrCreateByPath($path, 'file');
        $view = $this->videoViewRepository->firstOrCreateForUserAndVideo($user, $video);

        $isCached = in_array($videoPath->extension(), ['m2ts', 'avi', 'flv', 'vob'], true)
            ? $this->cacheService->isCached($video->hash)
            : false;

        if (in_array($videoPath->extension(), ['m2ts', 'avi', 'flv', 'vob'], true)) {
            $this->cacheService->ensure($fullPath, $video->hash);
        }

        return new VideoWatchDTO(
            filename: $filename,
            path: $path,
            hash: in_array($videoPath->extension(), ['m2ts', 'avi', 'flv', 'vob'], true) ? $video->hash : null,
            lastPosition: (int) ($view->last_position ?? 0),
            isFavorited: $this->favoriteRepository->existsForUserAndVideo($user, $video),
            isCached: $isCached,
            breadcrumbs: $this->pathService->breadcrumbs($path),
        );
    }

    public function updateProgress(User $user, string $path, int $time): void
    {
        $path = rawurldecode($path);
        $video = $this->videoRepository->findByPath($path);
        if (!$video) {
            return;
        }

        $this->videoViewRepository->updateProgress($user, $video, $time);
    }

    public function toggleWatched(User $user, string $path): void
    {
        $path = rawurldecode($path);
        $video = $this->videoRepository->firstOrCreateByPath($path, 'file');
        $this->videoViewRepository->toggleWatched($user, $video);
    }

    public function deleteCache(string $path): void
    {
        $video = $this->videoRepository->findByPath(rawurldecode($path));
        if ($video) {
            $this->cacheService->delete($video->hash);
        }
    }

    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
