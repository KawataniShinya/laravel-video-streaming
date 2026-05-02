<?php

namespace App\UseCase;

use App\DTOs\Admin\HlsCacheItemDTO;
use App\DTOs\Admin\HlsCacheListDTO;
use App\DTOs\Admin\HlsCacheSizeDTO;
use App\Models\User;
use App\Repositories\Interfaces\VideoRepositoryInterface;
use App\Services\Video\VideoCacheService;
use App\ValueObjects\Video\HlsCacheStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cache;

class AdminHlsCacheUseCase
{
    public function __construct(
        private readonly VideoRepositoryInterface $videoRepository,
        private readonly VideoCacheService $cacheService,
    ) {
    }

    public function list(User $user): HlsCacheListDTO
    {
        $this->authorizeAdmin($user);

        $caches = [];
        $knownVideos = $this->videoRepository->all()->pluck('path', 'hash')->toArray();
        $cacheBasePath = config('video.hls_cache_path', storage_path('hls'));

        if (File::exists($cacheBasePath)) {
            foreach (File::directories($cacheBasePath) as $dir) {
                $hash = basename($dir);
                $status = $this->cacheService->status($hash)->value;
                $sizeBytes = 0;

                $caches[] = new HlsCacheItemDTO(
                    hash: $hash,
                    path: $knownVideos[$hash] ?? 'Unknown (Source path not in database)',
                    size: null,
                    sizeBytes: $sizeBytes,
                    status: $status,
                );
            }
        }

        $caches = array_values($caches);
        usort($caches, static fn (HlsCacheItemDTO $a, HlsCacheItemDTO $b) => strcasecmp($a->path, $b->path));

        $diskPath = File::exists($cacheBasePath) ? $cacheBasePath : storage_path();
        $freeSpace = disk_free_space($diskPath);
        $totalDiskSpace = disk_total_space($diskPath);

        return new HlsCacheListDTO(
            caches: $caches,
            freeDiskSpace: $this->formatBytes($freeSpace),
            totalDiskSpace: $this->formatBytes($totalDiskSpace),
        );
    }

    public function size(User $user, string $hash): HlsCacheSizeDTO
    {
        $this->authorizeAdmin($user);
        $lock = Cache::lock('hls_size_calc_' . $user->id, 10);

        if (!$lock->get()) {
            abort(429, 'Another request is in progress');
        }

        try {
            $size = $this->cacheService->size($hash);

            return new HlsCacheSizeDTO(
                hash: $hash,
                sizeBytes: $size,
                sizeFormatted: $this->formatBytes($size),
            );
        } finally {
            $lock->release();
        }
    }

    public function delete(User $user, string $hash): void
    {
        $this->authorizeAdmin($user);
        $this->cacheService->delete($hash);
    }

    public function deleteMultiple(User $user, array $hashes): void
    {
        $this->authorizeAdmin($user);
        foreach ($hashes as $hash) {
            $this->cacheService->delete((string) $hash);
        }
    }

    public function deleteAll(User $user): void
    {
        $this->authorizeAdmin($user);
        $cacheBasePath = config('video.hls_cache_path', storage_path('hls'));
        if (!File::exists($cacheBasePath)) {
            return;
        }

        foreach (File::directories($cacheBasePath) as $dir) {
            $this->cacheService->delete(basename($dir));
        }
    }

    private function authorizeAdmin(User $user): void
    {
        if ($user->role !== 'admin') {
            abort(403, 'Unauthorized action.');
        }
    }

    private function formatBytes($bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max((float) $bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
