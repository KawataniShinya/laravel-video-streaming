<?php

namespace App\Http\Controllers;

use App\Services\VideoStream;
use App\Enums\Role;
use App\Models\VideoView;
use App\Models\Favorite;
use App\Models\Video as VideoModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Inertia\Inertia;

class VideoController extends Controller
{
    private string $videoRoot = '/videos';
    private string $hlsCachePath;

    public function __construct()
    {
        $this->hlsCachePath = storage_path('hls');
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    private function getOrCreateVideo($path, $type)
    {
        return VideoModel::firstOrCreate(
            ['path' => $path],
            ['hash' => md5($path), 'type' => $type]
        );
    }

    private function resolvePath($subpath)
    {
        if ($subpath) {
            $subpath = rawurldecode($subpath);
        }

        if (strpos($subpath, '..') !== false) {
            return null;
        }

        $path = $this->videoRoot;
        if ($subpath) {
            $path .= '/' . $subpath;
        }

        if (!File::exists($path)) {
            return null;
        }

        $realPath = realpath($path);
        $realRoot = realpath($this->videoRoot);

        if ($realPath === false || strpos($realPath, $realRoot) !== 0) {
            return null;
        }

        return $path;
    }

    private function getBreadcrumbs($path)
    {
        $breadcrumbs = [];
        if ($path) {
            $parts = explode('/', $path);
            $accumulated = '';
            foreach ($parts as $part) {
                if ($part === '') continue;
                $accumulated .= ($accumulated ? '/' : '') . $part;
                $breadcrumbs[] = ['name' => $part, 'path' => $accumulated];
            }
        }
        return $breadcrumbs;
    }

    public function index($path = null)
    {
        $fullPath = $this->resolvePath($path);
        $user = Auth::user();

        // Security check: User must have access to the current directory
        if (!$user->canAccessPath($path ?? '')) {
            return redirect()->route('dashboard')->with('error', 'You do not have access to this folder.');
        }

        if (!$fullPath || !File::isDirectory($fullPath)) {
            if ($fullPath && File::isFile($fullPath)) {
                return redirect()->route('videos.watch', ['path' => $path]);
            }
            abort(404);
        }

        // Pre-register or find current items in this directory to get IDs
        $items = [];

        // Scan directories
        $directories = File::directories($fullPath);
        foreach ($directories as $dir) {
            $relativePath = ($path ? $path . '/' : '') . basename($dir);

            // If user can access this path (either directly or as a parent of allowed paths)
            if (!$user->canAccessPath($relativePath)) continue;

            $video = $this->getOrCreateVideo($relativePath, 'folder');

            $items[] = [
                'id' => $video->id,
                'type' => 'folder',
                'name' => basename($dir),
                'path' => $relativePath,
            ];
        }

        // Scan files
        $files = File::files($fullPath);
        foreach ($files as $file) {
            $ext = strtolower($file->getExtension());
            if (in_array($ext, ['mp4', 'm2ts', 'avi', 'flv', 'vob'])) {
                $relativePath = ($path ? $path . '/' : '') . $file->getFilename();

                // Only include file if user can access it
                if (!$user->canAccessPath($relativePath)) continue;

                $video = $this->getOrCreateVideo($relativePath, 'file');

                $isCached = false;
                if (in_array($ext, ['m2ts', 'avi', 'flv', 'vob'])) {
                    $playlist = $this->hlsCachePath . '/' . $video->hash . '/index.m3u8';
                    $isCached = File::exists($playlist);
                }

                $items[] = [
                    'id' => $video->id,
                    'type' => 'file',
                    'name' => $file->getFilename(),
                    'path' => $relativePath,
                    'ext'  => $ext,
                    'size' => $this->formatBytes($file->getSize()),
                    'is_cached' => $isCached,
                ];
            }
        }

        // Get IDs of favorited and watched items for this user
        $itemIds = collect($items)->pluck('id');

        $watchedVideoIds = VideoView::where('user_id', Auth::id())
            ->whereIn('video_id', $itemIds)
            ->pluck('video_id')
            ->flip()
            ->toArray();

        $favoriteVideoIds = Favorite::where('user_id', Auth::id())
            ->whereIn('video_id', $itemIds)
            ->pluck('video_id')
            ->flip()
            ->toArray();

        // Attach statuses
        foreach ($items as &$item) {
            $isWatched = isset($watchedVideoIds[$item['id']]);
            $isFavorited = isset($favoriteVideoIds[$item['id']]);

            // For VOB files, only show status for the main entry point (VTS_01_1.VOB)
            if ($item['type'] === 'file' && isset($item['ext']) && $item['ext'] === 'vob') {
                if (strtoupper(basename($item['path'])) !== 'VTS_01_1.VOB') {
                    $isWatched = false;
                    $isFavorited = false;
                }
            }

            $item['is_watched'] = $isWatched;
            $item['is_favorited'] = $isFavorited;
        }

        return Inertia::render('Videos/Index', [
            'items' => $items,
            'currentPath' => $path,
            'breadcrumbs' => $this->getBreadcrumbs($path)
        ]);
    }

    public function history()
    {
        $user = Auth::user();
        $views = VideoView::with('video')
            ->where('user_id', $user->id)
            ->orderBy('updated_at', 'desc')
            ->get();

        $items = [];
        foreach ($views as $view) {
            $video = $view->video;
            if (!$video) continue;

            // Only show if user still has access to this path
            if (!$user->canAccessPath($video->path)) continue;

            $items[] = [
                'id' => $video->id,
                'name' => basename($video->path),
                'path' => $video->path,
                'last_position' => $view->last_position,
                'updated_at' => $view->updated_at->format('Y-m-d H:i'),
                'updated_at_human' => $view->updated_at->diffForHumans(),
            ];
        }

        return Inertia::render('Videos/History', [
            'items' => $items,
        ]);
    }

    public function watch($path)
    {
        $user = Auth::user();
        $path = rawurldecode($path);

        if (!$user->canAccessPath($path)) {
            return redirect()->route('dashboard')->with('error', 'You do not have access to this video.');
        }

        $fullPath = $this->resolvePath($path);

        if (!$fullPath || !File::isFile($fullPath)) {
            abort(404);
        }

        $filename = basename($fullPath);
        $directory = dirname($fullPath);

        // Redirect ALL VOB files to VTS_01_1.VOB if it exists (except the file itself)
        if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'vob' &&
            strtoupper($filename) !== 'VTS_01_1.VOB') {

            $potentialFiles = File::files($directory);
            foreach ($potentialFiles as $f) {
                if (strtoupper($f->getFilename()) === 'VTS_01_1.VOB') {
                    $relativePath = str_replace($this->videoRoot . '/', '', $f->getRealPath());
                    return redirect()->route('videos.watch', ['path' => $relativePath]);
                }
            }
        }

        $video = $this->getOrCreateVideo($path, 'file');

        // Mark as watched
        $view = VideoView::firstOrCreate([
            'user_id' => Auth::id(),
            'video_id' => $video->id,
        ]);

        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        $filename = basename($fullPath);

        $props = [
            'filename' => $filename,
            'path' => $path,
            'lastPosition' => $view->last_position ?? 0,
            'isFavorited' => Favorite::where('user_id', Auth::id())->where('video_id', $video->id)->exists(),
            'breadcrumbs' => $this->getBreadcrumbs($path),
        ];

        if ($ext === 'mp4') {
            return Inertia::render('Videos/WatchMp4', $props);
        } elseif (in_array($ext, ['m2ts', 'avi', 'flv', 'vob'])) {
            $this->ensureHls($fullPath, $video->hash);
            $props['hash'] = $video->hash;
            return Inertia::render('Videos/WatchHls', $props);
        }

        abort(404);
    }
    public function stream($path)
    {
        $user = Auth::user();
        $path = rawurldecode($path);

        if (!$user->canAccessPath($path)) {
            abort(403);
        }

        $fullPath = $this->resolvePath($path);
        if (!$fullPath || !File::isFile($fullPath)) abort(404);

        $stream = new VideoStream($fullPath);
        $stream->start();
    }

    public function updateProgress(Request $request)
    {
        $user = Auth::user();
        $path = rawurldecode($request->input('path'));
        $time = $request->input('time');

        if (!$user->canAccessPath($path)) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        if (!$path || !is_numeric($time)) return response()->json(['status' => 'error'], 400);

        $video = VideoModel::where('path', $path)->first();
        if (!$video) return response()->json(['status' => 'error'], 404);

        VideoView::updateOrCreate(
            ['user_id' => Auth::id(), 'video_id' => $video->id],
            ['last_position' => (int) $time]
        );

        return response()->json(['status' => 'success']);
    }

    private function ensureHls($inputPath, $hash)
    {
        $outputDir = $this->hlsCachePath . '/' . $hash;
        $playlist = $outputDir . '/index.m3u8';
        $pidFile = $outputDir . '/ffmpeg.pid';

        if (!File::exists($outputDir)) {
            File::makeDirectory($outputDir, 0755, true);
        }

        // Check if already transcoding
        if (File::exists($pidFile)) {
            $pid = trim(File::get($pidFile));
            if (is_numeric($pid) && $this->isProcessRunning($pid)) {
                // Already running, just return
                return;
            }
        }

        if (!File::exists($playlist)) {
            $ext = strtolower(pathinfo($inputPath, PATHINFO_EXTENSION));
            $inputArg = escapeshellarg($inputPath);

            if ($ext === 'vob') {
                $directory = dirname($inputPath);
                $fileName = basename($inputPath);
                $prefix = null;

                if (preg_match('/^(VTS_\d+)_/i', $fileName, $matches)) {
                    $prefix = strtoupper($matches[1]);
                }

                if ($prefix) {
                    $vobFiles = [];
                    foreach (File::files($directory) as $file) {
                        $currentName = strtoupper($file->getFilename());
                        if (str_starts_with($currentName, $prefix . '_') && str_ends_with($currentName, '.VOB') && !str_ends_with($currentName, '_0.VOB')) {
                            $vobFiles[] = $file->getRealPath();
                        }
                    }
                    sort($vobFiles);
                    if (!empty($vobFiles)) {
                        $concatString = 'concat:' . implode('|', $vobFiles);
                        $inputArg = escapeshellarg($concatString);
                    }
                }
            }

            // Detect audio streams to handle single, multi, or no audio correctly
            $probeCmd = "ffmpeg -i " . $inputArg . " 2>&1 | grep 'Stream #0' | grep 'Audio:' | wc -l";
            $audioCount = (int) shell_exec($probeCmd);

            // Build stream mapping
            $videoMaps = "-map 0:v:0 -map 0:v:0"; // Two video variants
            $audioMaps = "";
            $streamMap = "v:0,agroup:aud,name:High v:1,agroup:aud,name:Low ";

            if ($ext === 'vob') {
                // DVD multi-audio (up to 2 tracks)
                for ($i = 0; $i < min($audioCount, 2); $i++) {
                    $streamMap .= "a:$i,agroup:aud,name:Track" . ($i + 1) . " ";
                    $audioMaps .= " -map 0:a:$i";
                }
            } else {
                // Other formats: limit to 1 audio track for maximum compatibility
                if ($audioCount > 0) {
                    $streamMap .= "a:0,agroup:aud,name:Audio ";
                    $audioMaps = "-map 0:a:0";
                }
            }

            $ffmpegLogPath = $outputDir . '/ffmpeg.log';

            // Filters: High keeps original size (with deinterlace for VOB), Low scales to 360p
            $vfHigh = ($ext === 'vob') ? "yadif" : "null";
            $vfLow  = ($ext === 'vob') ? "yadif,scale=-2:360" : "scale=-2:360";

            // Common flags for stability (critical for m2ts/vob)
            $hlsFlags = "-fflags +genpts+igndts -avoid_negative_ts make_zero";

            $cmd = "nohup ffmpeg -analyzeduration 100M -probesize 100M -i " . $inputArg . " " .
                   $videoMaps . " " . $audioMaps . " " .
                   // Video 0: High Quality (Original)
                   "-c:v:0 libx264 -preset ultrafast -pix_fmt yuv420p -filter:v:0 " . $vfHigh . " " .
                   // Video 1: Low Quality (360p)
                   "-c:v:1 libx264 -preset ultrafast -pix_fmt yuv420p -filter:v:1 " . $vfLow . " -b:v:1 800k -maxrate:v:1 1200k -bufsize:v:1 1600k " .
                   // Audio settings
                   ($audioCount > 0 ? "-c:a aac -ac 2 -ar 44100 " : "") .
                   "-f hls -hls_time 10 -hls_list_size 0 -hls_playlist_type event " .
                   "-master_pl_name index.m3u8 " .
                   "-hls_segment_filename " . escapeshellarg($outputDir . "/s%v_%d.ts") . " " .
                   "-var_stream_map \"" . trim($streamMap) . "\" " .
                   $hlsFlags . " " .
                   escapeshellarg($outputDir . "/p%v.m3u8") . " > " . escapeshellarg($ffmpegLogPath) . " 2>&1 & echo $! > " . escapeshellarg($pidFile);

            Process::run($cmd);
        }
    }

    private function isProcessRunning($pid)
    {
        $output = shell_exec("ps -p $pid");
        return strpos($output, (string)$pid) !== false;
    }

    public function serveHls($hash, $file)
    {
        // Support files in subdirectories (e.g., v0/index.m3u8)
        $path = $this->hlsCachePath . '/' . $hash . '/' . $file;

        // If not found directly, check if it's a relative path request within the hash dir
        if (!File::exists($path)) {
            abort(404);
        }

        return response()->file($path);
    }

    public function deleteCache(Request $request)
    {
        if (Auth::user()->role !== Role::Admin->value) abort(403);

        $path = $request->input('path');
        if (!$path) return redirect()->route('videos.index');

        $video = VideoModel::where('path', rawurldecode($path))->first();
        if ($video) {
            $this->stopAndRemoveCache($video->hash);
        }

        return redirect()->route('videos.index', ['path' => $path ? dirname(rawurldecode($path)) : null]);
    }

    private function stopAndRemoveCache($hash)
    {
        $cacheDir = $this->hlsCachePath . '/' . $hash;
        if (File::exists($cacheDir)) {
            $pidFile = $cacheDir . '/ffmpeg.pid';
            if (File::exists($pidFile)) {
                $pid = trim(File::get($pidFile));
                if (is_numeric($pid)) {
                    // Kill the process and its children
                    shell_exec("kill -9 $pid > /dev/null 2>&1");
                }
            }
            File::deleteDirectory($cacheDir);
        }
    }

    public function toggleWatchStatus(Request $request)
    {
        $path = $request->input('path');
        if (!$path) return redirect()->back();

        $video = $this->getOrCreateVideo(rawurldecode($path), 'file');
        $view = VideoView::where('user_id', Auth::id())->where('video_id', $video->id)->first();

        if ($view) {
            $view->delete();
        } else {
            VideoView::create(['user_id' => Auth::id(), 'video_id' => $video->id]);
        }

        return redirect()->back();
    }
}
