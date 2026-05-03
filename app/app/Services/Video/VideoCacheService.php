<?php

namespace App\Services\Video;

use App\ValueObjects\Video\HlsCacheStatus;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class VideoCacheService
{
    public function __construct(
        private readonly string $hlsCachePath = ''
    ) {
    }

    private function cacheBasePath(): string
    {
        return $this->hlsCachePath !== ''
            ? $this->hlsCachePath
            : config('video.hls_cache_path', storage_path('hls'));
    }

    public function cacheDir(string $hash): string
    {
        return $this->cacheBasePath() . '/' . $hash;
    }

    public function playlistPath(string $hash): string
    {
        return $this->cacheDir($hash) . '/index.m3u8';
    }

    public function lockPath(string $hash): string
    {
        return $this->cacheDir($hash) . '/transcoding.lock';
    }

    public function pidPath(string $hash): string
    {
        return $this->cacheDir($hash) . '/ffmpeg.pid';
    }

    public function logPath(string $hash): string
    {
        return $this->cacheDir($hash) . '/ffmpeg.log';
    }

    public function durationPath(string $hash): string
    {
        return $this->cacheDir($hash) . '/total_duration.txt';
    }

    public function isCached(string $hash): bool
    {
        return $this->status($hash) === HlsCacheStatus::Completed;
    }

    public function progress(string $hash): ?float
    {
        $durationFile = $this->durationPath($hash);
        $logFile = $this->logPath($hash);

        if (!File::exists($durationFile) || !File::exists($logFile)) {
            return null;
        }

        $totalDuration = (float) trim(File::get($durationFile));
        if ($totalDuration <= 0) {
            return null;
        }

        // Get the last 'time=HH:MM:SS.ms' from the log
        $process = Process::run("tail -n 100 " . escapeshellarg($logFile) . " | grep -o 'time=[0-9:.]*' | tail -n 1");
        $output = $process->output();
        
        if (!$output) {
            return 0.0;
        }

        $timeStr = str_replace('time=', '', trim($output));
        $parts = explode(':', $timeStr);
        if (count($parts) === 3) {
            $currentSeconds = ($parts[0] * 3600) + ($parts[1] * 60) + (float)$parts[2];
            $progress = ($currentSeconds / $totalDuration) * 100;

            return min(100.0, max(0.0, $progress));
        }

        return 0.0;
    }

    public function status(string $hash): HlsCacheStatus
    {
        $dir = $this->cacheDir($hash);
        $playlist = $this->playlistPath($hash);
        $lockFile = $this->lockPath($hash);
        $pidFile = $this->pidPath($hash);

        $isRunning = false;
        if (File::exists($pidFile)) {
            $pid = trim(File::get($pidFile));
            if (is_numeric($pid)) {
                $isRunning = $this->isProcessRunning($pid);
            }
        }

        if ($isRunning) {
            return HlsCacheStatus::Transcoding;
        }

        if (File::exists($playlist) && !File::exists($lockFile)) {
            return HlsCacheStatus::Completed;
        }

        return HlsCacheStatus::Failed;
    }

    public function ensureDirectory(string $hash): void
    {
        $dir = $this->cacheDir($hash);
        if (!File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }
    }

    public function delete(string $hash): void
    {
        $cacheDir = $this->cacheDir($hash);
        if (!File::exists($cacheDir)) {
            return;
        }

        $pidFile = $this->pidPath($hash);
        if (File::exists($pidFile)) {
            $pid = trim(File::get($pidFile));
            if (is_numeric($pid)) {
                Process::run("kill -9 $pid");
            }
        }

        File::deleteDirectory($cacheDir);
    }

    public function size(string $hash): int
    {
        $path = $this->cacheDir($hash);
        if (!File::exists($path)) {
            return 0;
        }

        $process = Process::run("du -sk " . escapeshellarg($path));
        $output = $process->output();
        
        if ($output) {
            $parts = preg_split('/\s+/', trim($output));
            if (isset($parts[0]) && is_numeric($parts[0])) {
                return (int) $parts[0] * 1024;
            }
        }

        return collect(File::allFiles($path))->sum(function ($file) {
            return $file->getSize();
        });
    }

    public function ensure(string $inputPath, string $hash): void
    {
        $outputDir = $this->cacheDir($hash);
        $playlist = $this->playlistPath($hash);
        $pidFile = $this->pidPath($hash);
        $lockFile = $this->lockPath($hash);
        $ffmpegLogPath = $this->logPath($hash);
        $durationPath = $this->durationPath($hash);

        $this->ensureDirectory($hash);

        if (File::exists($pidFile)) {
            $pid = trim(File::get($pidFile));
            if (is_numeric($pid) && $this->isProcessRunning($pid)) {
                return;
            }
        }

        if (!File::exists($playlist) || File::exists($lockFile)) {
            $ext = strtolower(pathinfo($inputPath, PATHINFO_EXTENSION));
            $inputArg = $this->buildInputArg($inputPath, $ext);

            // Probe duration and save it
            $totalDuration = $this->getTotalDuration($inputArg);
            if ($totalDuration > 0) {
                File::put($durationPath, (string)$totalDuration);
            }

            $audioCount = $this->probeAudioStreamCount($inputArg);
            $videoMaps = "-map 0:v:0 -map 0:v:0";
            $audioMaps = '';
            $streamMap = 'v:0,agroup:aud,name:High v:1,agroup:aud,name:Low ';

            if ($ext === 'vob') {
                for ($i = 0; $i < min($audioCount, 2); $i++) {
                    $streamMap .= "a:$i,agroup:aud,name:Track" . ($i + 1) . ' ';
                    $audioMaps .= " -map 0:a:$i";
                }
            } elseif ($audioCount > 0) {
                $streamMap .= 'a:0,agroup:aud,name:Audio ';
                $audioMaps = '-map 0:a:0';
            }

            $vfHigh = ($ext === 'vob') ? 'yadif' : 'null';
            $vfLow = ($ext === 'vob') ? 'yadif,scale=-2:360' : 'scale=-2:360';
            $hlsFlags = '-fflags +genpts+igndts -avoid_negative_ts make_zero';
            $innerCmd = $this->buildInnerCommand(
                $lockFile,
                $inputArg,
                $videoMaps,
                $audioMaps,
                $vfHigh,
                $vfLow,
                $streamMap,
                $outputDir,
                $hlsFlags
            );

            $cmd = "nohup sh -c " . escapeshellarg($innerCmd) . " > " . escapeshellarg($ffmpegLogPath) . " 2>&1 & echo $! > " . escapeshellarg($pidFile);

            Process::run($cmd);
        }
    }

    private function buildInputArg(string $inputPath, string $ext): string
    {
        if ($ext !== 'vob') {
            return '-i ' . escapeshellarg($inputPath);
        }

        $directory = dirname($inputPath);
        $fileName = basename($inputPath);
        $prefix = null;

        if (preg_match('/^(VTS_\d+)_/i', $fileName, $matches)) {
            $prefix = strtoupper($matches[1]);
        }

        if (!$prefix) {
            return '-i ' . escapeshellarg($inputPath);
        }

        $vobFiles = [];
        foreach (File::files($directory) as $file) {
            $currentName = strtoupper($file->getFilename());
            if (str_starts_with($currentName, $prefix . '_') && str_ends_with($currentName, '.VOB') && !str_ends_with($currentName, '_0.VOB')) {
                $vobFiles[] = $file->getRealPath();
            }
        }

        sort($vobFiles, SORT_NATURAL);

        if (empty($vobFiles)) {
            return '-i ' . escapeshellarg($inputPath);
        }

        return '-i ' . escapeshellarg('concat:' . implode('|', $vobFiles));
    }

    private function probeAudioStreamCount(string $inputArg): int
    {
        $process = Process::run('ffmpeg ' . $inputArg . " 2>&1 | grep 'Stream #0' | grep 'Audio:' | wc -l");

        return (int) trim($process->output());
    }

    private function getTotalDuration(string $inputArg): float
    {
        $process = Process::run("ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . $inputArg);
        $output = $process->output();

        return is_numeric(trim((string)$output)) ? (float)trim($output) : 0.0;
    }

    private function buildInnerCommand(
        string $lockFile,
        string $inputArg,
        string $videoMaps,
        string $audioMaps,
        string $vfHigh,
        string $vfLow,
        string $streamMap,
        string $outputDir,
        string $hlsFlags
    ): string {
        return "touch " . escapeshellarg($lockFile) . " && ffmpeg -analyzeduration 100M -probesize 100M " . $inputArg . " " .
            $videoMaps . " " . $audioMaps . " " .
            "-c:v:0 libx264 -preset ultrafast -pix_fmt yuv420p -filter:v:0 " . $vfHigh . " " .
            "-c:v:1 libx264 -preset ultrafast -pix_fmt yuv420p -filter:v:1 " . $vfLow . " -b:v:1 800k -maxrate:v:1 1200k -bufsize:v:1 1600k " .
            ($audioMaps !== '' ? '-c:a aac -ac 2 -ar 44100 ' : '') .
            '-f hls -hls_time 10 -hls_list_size 0 -hls_playlist_type event ' .
            '-master_pl_name index.m3u8 ' .
            '-hls_segment_filename ' . escapeshellarg($outputDir . '/s%v_%d.ts') . ' ' .
            '-var_stream_map "' . trim($streamMap) . '" ' .
            $hlsFlags . ' ' .
            escapeshellarg($outputDir . '/p%v.m3u8') . ' && rm ' . escapeshellarg($lockFile);
    }

    private function isProcessRunning($pid): bool
    {
        $process = Process::run("ps -p $pid");

        return strpos((string) $process->output(), (string) $pid) !== false;
    }
}
