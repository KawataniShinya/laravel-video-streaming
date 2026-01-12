<?php

namespace App\Http\Controllers;

use App\Services\VideoStream;
use Illuminate\Http\Request;
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

    /**
     * Resolve the real path and ensure it's within the video root.
     */
    private function resolvePath($subpath)
    {
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
            return null;
        }

        // Use realpath to strictly verify it is inside videoRoot
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
            // If it's a file, redirect to watch
            if ($fullPath && File::isFile($fullPath)) {
                return redirect()->route('videos.watch', ['path' => $path]);
            }
            abort(404);
        }

        $items = [];
        // Scan directories
        $directories = File::directories($fullPath);
        foreach ($directories as $dir) {
            $items[] = [
                'type' => 'folder',
                'name' => basename($dir),
                'path' => ($path ? $path . '/' : '') . basename($dir),
            ];
        }

        // Scan files
        $files = File::files($fullPath);
        foreach ($files as $file) {
            $ext = strtolower($file->getExtension());
            if (in_array($ext, ['mp4', 'm2ts', 'avi', 'flv', 'vob'])) {
                $items[] = [
                    'type' => 'file',
                    'name' => $file->getFilename(),
                    'path' => ($path ? $path . '/' : '') . $file->getFilename(),
                    'ext'  => $ext
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

        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        $filename = basename($fullPath);

        if ($ext === 'mp4') {
            return Inertia::render('Videos/WatchMp4', [
                'path' => $path,
                'filename' => $filename
            ]);
        } elseif (in_array($ext, ['m2ts', 'avi', 'flv', 'vob'])) {
            $hash = md5($path); // Use path hash for cache directory
            $this->ensureHls($fullPath, $hash);
            
            return Inertia::render('Videos/WatchHls', [
                'hash' => $hash, 
                'filename' => $filename, 
                'path' => $path
            ]);
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
                    
                    // Exclude VIDEO_TS.VOB and menu VOBs (ending in 0.VOB)
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
}