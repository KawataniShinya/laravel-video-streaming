<?php

namespace App\Services\Video;

use App\DTOs\Video\BreadcrumbDTO;
use App\ValueObjects\Video\VideoPath;
use Illuminate\Support\Facades\File;

class VideoPathService
{
    public function __construct(
        private readonly string $videoRoot = ''
    ) {
    }

    private function rootPath(): string
    {
        return $this->videoRoot !== ''
            ? $this->videoRoot
            : config('video.root', '/videos');
    }

    public function resolvePath(?string $subpath): ?string
    {
        if ($subpath) {
            $subpath = rawurldecode($subpath);
        }

        if ($subpath !== null && str_contains($subpath, '..')) {
            return null;
        }

        $path = $this->rootPath();
        if ($subpath) {
            $path .= '/' . $subpath;
        }

        if (!File::exists($path)) {
            return null;
        }

        $realPath = realpath($path);
        $realRoot = realpath($this->rootPath());

        if ($realPath === false || $realRoot === false || !str_starts_with($realPath, $realRoot)) {
            return null;
        }

        return $path;
    }

    /**
     * @return BreadcrumbDTO[]
     */
    public function breadcrumbs(?string $path): array
    {
        $breadcrumbs = [];

        if ($path) {
            $parts = explode('/', $path);
            $accumulated = '';
            foreach ($parts as $part) {
                if ($part === '') {
                    continue;
                }

                $accumulated .= ($accumulated ? '/' : '') . $part;
                $breadcrumbs[] = new BreadcrumbDTO($part, $accumulated);
            }
        }

        return $breadcrumbs;
    }

    public function normalizePrimaryVobPath(string $path): ?string
    {
        $videoPath = new VideoPath($path);
        if (!$videoPath->isVob() || $videoPath->isVobMainEntry()) {
            return $path;
        }

        $resolved = $this->resolvePath($videoPath->directory());
        if (!$resolved) {
            return null;
        }

        foreach (File::files($resolved) as $file) {
            if (strtoupper($file->getFilename()) === 'VTS_01_1.VOB') {
                return str_replace($this->rootPath() . '/', '', $file->getRealPath());
            }
        }

        return $path;
    }

    public function parentPath(?string $path): ?string
    {
        return (new VideoPath($path ?? ''))->parent();
    }
}
