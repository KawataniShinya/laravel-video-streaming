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
    private $videoRoot = '/videos';
    private $hlsCachePath;

    public function __construct()
    {
        $this->hlsCachePath = storage_path('hls');
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

    public function index($path = null)
    {
        $fullPath = $this->resolvePath($path);

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
            $item['is_watched'] = isset($watchedVideoIds[$item['id']]);
            $item['is_favorited'] = isset($favoriteVideoIds[$item['id']]);
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

        $video = $this->getOrCreateVideo(rawurldecode($path), 'file');

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
        $fullPath = $this->resolvePath($path);
        if (!$fullPath || !File::isFile($fullPath)) abort(404);

        $stream = new VideoStream($fullPath);
        $stream->start();
    }

    public function updateProgress(Request $request)
    {
        $path = $request->input('path');
        $time = $request->input('time');
        if (!$path || !is_numeric($time)) return response()->json(['status' => 'error'], 400);

        $video = VideoModel::where('path', rawurldecode($path))->first();
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

        if (!File::exists($outputDir)) {
            File::makeDirectory($outputDir, 0755, true);
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

            $cmd = "nohup ffmpeg -i " . $inputArg . " -map 0:v -map 0:a? -c:v libx264 -c:a aac -f hls -hls_time 10 -hls_list_size 0 -fflags +genpts -avoid_negative_ts make_zero " . escapeshellarg($playlist) . " > /dev/null 2>&1 &";
            Process::run($cmd);
        }
    }

    public function serveHls($hash, $file)
    {
        $path = $this->hlsCachePath . '/' . $hash . '/' . $file;
        if (!File::exists($path)) abort(404);
        return response()->file($path);
    }

    public function deleteCache(Request $request)
    {
        if (Auth::user()->role !== Role::Admin->value) abort(403);

        $path = $request->input('path');
        if (!$path) return redirect()->back();

        $video = VideoModel::where('path', rawurldecode($path))->first();
        if ($video) {
            $cacheDir = $this->hlsCachePath . '/' . $video->hash;
            if (File::exists($cacheDir)) File::deleteDirectory($cacheDir);
        }

        return redirect()->back();
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
