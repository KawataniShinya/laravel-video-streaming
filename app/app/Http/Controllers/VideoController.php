<?php

namespace App\Http\Controllers;

use App\Services\VideoStream;
use App\Enums\Role;
use App\Models\VideoView;
use App\Models\Favorite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class VideoController extends Controller
{
    private $videoRoot = '/videos';
    private $hlsCachePath;

    public function __construct()
    {
        $this->hlsCachePath = storage_path('hls');
    }

    /**
     * Resolve the real path and ensure it's within the video root.
     */
    private function resolvePath($subpath)
    {
        if ($subpath) {
            $subpath = rawurldecode($subpath);
        }

        Log::info('Resolving path: ' . $subpath);

        // Prevent directory traversal
        if (strpos($subpath, '..') !== false) {
            return null;
        }

        $path = $this->videoRoot;
        if ($subpath) {
            $path .= '/' . $subpath;
        }

        // Check if file/dir exists
        if (!File::exists($path)) {
            Log::warning('File does not exist: ' . $path);
            return null;
        }

        // Use realpath to strictly verify it is inside videoRoot
        $realPath = realpath($path);
        $realRoot = realpath($this->videoRoot);

        if ($realPath === false || strpos($realPath, $realRoot) !== 0) {
            Log::warning('Path outside root: ' . $realPath);
            return null;
        }

        return $path;
    }

    public function index($path = null)
    {
        $fullPath = $this->resolvePath($path);

        if (!$fullPath || !File::isDirectory($fullPath)) {
            // If it's a file, redirect to watch
            if ($fullPath && File::isFile($fullPath)) {
                return redirect()->route('videos.watch', ['path' => $path]);
            }
            abort(404);
        }

        // Get watched videos and favorites for the current user
        $watchedVideos = VideoView::where('user_id', Auth::id())
            ->pluck('video_path')
            ->flip()
            ->toArray();
            
        $favorites = Favorite::where('user_id', Auth::id())
            ->pluck('path')
            ->flip()
            ->toArray();

        $items = [];
        // Scan directories
        $directories = File::directories($fullPath);
        foreach ($directories as $dir) {
            $relativePath = ($path ? $path . '/' : '') . basename($dir);
            $items[] = [
                'type' => 'folder',
                'name' => basename($dir),
                'path' => $relativePath,
                'is_favorited' => isset($favorites[$relativePath]),
            ];
        }

        // Scan files
        $files = File::files($fullPath);
        foreach ($files as $file) {
            $ext = strtolower($file->getExtension());
            if (in_array($ext, ['mp4', 'm2ts', 'avi', 'flv', 'vob'])) {
                $relativePath = ($path ? $path . '/' : '') . $file->getFilename();
                $isCached = false;
                if (in_array($ext, ['m2ts', 'avi', 'flv', 'vob'])) {
                    $hash = md5($relativePath);
                    $playlist = $this->hlsCachePath . '/' . $hash . '/index.m3u8';
                    $isCached = File::exists($playlist);
                }

                $items[] = [
                    'type' => 'file',
                    'name' => $file->getFilename(),
                    'path' => $relativePath,
                    'ext'  => $ext,
                    'is_cached' => $isCached,
                    'is_watched' => isset($watchedVideos[$relativePath]),
                    'is_favorited' => isset($favorites[$relativePath]),
                ];
            }
        }

        // Breadcrumbs
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

        return Inertia::render('Videos/Index', [
            'items' => $items,
            'currentPath' => $path,
            'breadcrumbs' => $breadcrumbs
        ]);
    }

    public function watch($path)
    {
        $fullPath = $this->resolvePath($path);

        if (!$fullPath || !File::isFile($fullPath)) {
            abort(404);
        }

        // Mark as watched and get last position
        $view = VideoView::firstOrCreate([
            'user_id' => Auth::id(),
            'video_path' => rawurldecode($path),
        ]);

        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        $filename = basename($fullPath);

        $props = [
            'filename' => $filename,
            'path' => $path,
            'lastPosition' => $view->last_position ?? 0,
            'isFavorited' => Favorite::where('user_id', Auth::id())->where('path', rawurldecode($path))->exists(),
        ];

        if ($ext === 'mp4') {
            return Inertia::render('Videos/WatchMp4', $props);
        } elseif (in_array($ext, ['m2ts', 'avi', 'flv', 'vob'])) {
            $hash = md5(rawurldecode($path)); // Use path hash for cache directory
            $this->ensureHls($fullPath, $hash);

            $props['hash'] = $hash;
            return Inertia::render('Videos/WatchHls', $props);
        }

        abort(404);
    }

    public function stream($path)
    {
        $fullPath = $this->resolvePath($path);

        if (!$fullPath || !File::isFile($fullPath)) {
            abort(404);
        }

        $stream = new VideoStream($fullPath);
        $stream->start();
    }

    public function updateProgress(Request $request)
    {
        $path = $request->input('path');
        $time = $request->input('time');

        if (!$path || !is_numeric($time)) {
            return response()->json(['status' => 'error'], 400);
        }

        VideoView::updateOrCreate(
            ['user_id' => Auth::id(), 'video_path' => rawurldecode($path)],
            ['last_position' => (int) $time]
        );

        return response()->json(['status' => 'success']);
    }

    private function ensureHls($inputPath, $hash)
    {
        $outputDir = $this->hlsCachePath . '/' . $hash;
        $playlist = $outputDir . '/index.m3u8';

        if (!File::exists($outputDir)) {
            File::makeDirectory($outputDir, 0755, true);
        }

        if (!File::exists($playlist)) {
            $ext = strtolower(pathinfo($inputPath, PATHINFO_EXTENSION));
            $inputArg = escapeshellarg($inputPath);

            if ($ext === 'vob') {
                $directory = dirname($inputPath);
                $files = File::files($directory);
                $vobFiles = [];
                foreach ($files as $file) {
                    $fileName = $file->getFilename();
                    $fileExt = strtolower($file->getExtension());

                    if ($fileExt === 'vob' &&
                        strtoupper($fileName) !== 'VIDEO_TS.VOB' &&
                        !str_ends_with(strtoupper($fileName), '0.VOB')) {

                        $vobFiles[] = $file->getRealPath();
                    }
                }
                sort($vobFiles);
                $concatString = 'concat:' . implode('|', $vobFiles);
                $inputArg = escapeshellarg($concatString);
            }

            // Start conversion in background
            $cmd = "nohup ffmpeg -i " . $inputArg . " -map 0:v -map 0:a -c:v libx264 -c:a aac -f hls -hls_time 10 -hls_list_size 0 " . escapeshellarg($playlist) . " > /dev/null 2>&1 &";
            Process::run($cmd);
        }
    }

    public function serveHls($hash, $file)
    {
        $path = $this->hlsCachePath . '/' . $hash . '/' . $file;

        if (!File::exists($path)) {
            abort(404);
        }

        return response()->file($path);
    }

    public function deleteCache(Request $request)
    {
        if (Auth::user()->role !== Role::Admin->value) {
            abort(403, 'Unauthorized action.');
        }

        $path = $request->input('path');
        if (!$path) {
            return redirect()->back();
        }

        $hash = md5(rawurldecode($path));
        $cacheDir = $this->hlsCachePath . '/' . $hash;

        if (File::exists($cacheDir)) {
            File::deleteDirectory($cacheDir);
        }

        return redirect()->back();
    }

    public function toggleWatchStatus(Request $request)
    {
        $path = $request->input('path');
        if (!$path) {
            return redirect()->back();
        }

        $userId = Auth::id();
        $decodedPath = rawurldecode($path);
        $view = VideoView::where('user_id', $userId)->where('video_path', $decodedPath)->first();

        if ($view) {
            $view->delete();
        } else {
            VideoView::create([
                'user_id' => $userId,
                'video_path' => $decodedPath,
            ]);
        }

        return redirect()->back();
    }
}