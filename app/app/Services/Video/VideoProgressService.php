<?php

namespace App\Services\Video;

use App\Models\Video;

class VideoProgressService
{
    public function __construct(private readonly VideoCacheService $cache) {}

    public function duration(Video $video): ?float
    {
        if ($video->type !== 'file') {
            return null;
        }

        if (strtolower(pathinfo($video->path, PATHINFO_EXTENSION)) !== 'mp4') {
            return $this->completedHlsDuration($video->hash);
        }

        $duration = (float) $video->duration;
        if (!is_finite($duration) || $duration <= 0) {
            return null;
        }

        return $duration;
    }

    private function completedHlsDuration(string $hash): ?float
    {
        // ffprobe's source estimate can be wrong for concatenated DVDs. Only
        // a completed video playlist describes the timeline users actually play.
        if (is_file($this->cache->lockPath($hash))) {
            return null;
        }
        $file = $this->cache->cacheDir($hash) . '/pHigh.m3u8';
        if (!is_file($file)) {
            return null;
        }
        $playlist = file_get_contents($file);
        if ($playlist === false || !preg_match('/^#EXT-X-ENDLIST\s*$/m', $playlist)) {
            return null;
        }
        preg_match_all('/^#EXTINF:([0-9]+(?:\.[0-9]+)?),/m', $playlist, $matches);
        $duration = array_sum(array_map('floatval', $matches[1]));

        return is_finite($duration) && $duration > 0 ? $duration : null;
    }

    public function percentage(Video $video, int $position): ?float
    {
        $duration = $this->duration($video);

        if ($duration === null) {
            return null;
        }
        // Positions are saved as whole seconds, including the final position.
        if ($position > 0 && $position >= floor($duration)) {
            return 100.0;
        }

        return floor(min(100, max(0, $position / $duration * 100)) * 10) / 10;
    }
}
