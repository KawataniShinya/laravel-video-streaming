<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Video as VideoModel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Http\Request;
use Inertia\Inertia;

use Illuminate\Support\Facades\Cache;

class HlsCacheController extends Controller
{
    private $hlsCachePath;

    public function __construct()
    {
        $this->hlsCachePath = storage_path('hls');
    }

    private function authorizeAdmin()
    {
        if (Auth::user()->role !== Role::Admin->value) {
            abort(403, 'Unauthorized action.');
        }
    }

    private function getDirectorySize($path)
    {
        if (!File::exists($path)) return 0;
        
        // Use the system 'du' command for much faster directory size calculation
        $output = shell_exec("du -sb " . escapeshellarg($path));
        if ($output) {
            $parts = explode("\t", $output);
            return (int) $parts[0];
        }
        
        return 0;
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

    public function index()
    {
        $this->authorizeAdmin();

        $caches = [];

        // Map hashes using the videos master table
        $knownVideos = VideoModel::all()->pluck('path', 'hash')->toArray();

        if (File::exists($this->hlsCachePath)) {
            $directories = File::directories($this->hlsCachePath);
            foreach ($directories as $dir) {
                $hash = basename($dir);

                $pidFile = $dir . '/ffmpeg.pid';
                $isRunning = false;
                if (File::exists($pidFile)) {
                    $pid = trim(File::get($pidFile));
                    if (is_numeric($pid)) {
                        $isRunning = $this->isProcessRunning($pid);
                    }
                }

                $hasIndex = File::exists($dir . '/index.m3u8');
                
                $status = 'failed';
                if ($isRunning) {
                    $status = 'transcoding';
                } elseif ($hasIndex) {
                    $status = 'completed';
                }

                $caches[] = [
                    'hash' => $hash,
                    'path' => $knownVideos[$hash] ?? 'Unknown (Source path not in database)',
                    'size' => null, // To be fetched asynchronously
                    'size_bytes' => 0,
                    'status' => $status,
                ];
            }
        }

        // Default sort by path
        usort($caches, function ($a, $b) {
            return strcasecmp($a['path'], $b['path']);
        });

        $diskPath = $this->hlsCachePath;
        if (!File::exists($diskPath)) {
            $diskPath = storage_path();
        }
        $freeSpace = disk_free_space($diskPath);
        $totalDiskSpace = disk_total_space($diskPath);

        return Inertia::render('Admin/HlsCache/Index', [
            'caches' => $caches,
            'freeDiskSpace' => $this->formatBytes($freeSpace),
            'totalDiskSpace' => $this->formatBytes($totalDiskSpace),
        ]);
    }

    public function getSize($hash)
    {
        $this->authorizeAdmin();
        
        $lock = Cache::lock('hls_size_calc_' . Auth::id(), 10);
        
        if (!$lock->get()) {
            return response()->json(['message' => 'Another request is in progress'], 429);
        }

        try {
            $dir = $this->hlsCachePath . '/' . $hash;
            $size = $this->getDirectorySize($dir);
            
            return response()->json([
                'hash' => $hash,
                'size_bytes' => $size,
                'size_formatted' => $this->formatBytes($size)
            ]);
        } finally {
            $lock->release();
        }
    }

    private function isProcessRunning($pid)
    {
        $output = shell_exec("ps -p $pid");
        return strpos($output, (string)$pid) !== false;
    }

    public function destroy($hash)
    {
        $this->authorizeAdmin();
        $this->stopAndRemoveCache($hash);
        return redirect()->route('admin.hls.index');
    }

    public function destroyMultiple(Request $request)
    {
        $this->authorizeAdmin();
        $hashes = $request->input('hashes', []);
        
        foreach ($hashes as $hash) {
            $this->stopAndRemoveCache($hash);
        }
        
        return redirect()->route('admin.hls.index');
    }

    public function destroyAll()
    {
        $this->authorizeAdmin();
        if (File::exists($this->hlsCachePath)) {
            $directories = File::directories($this->hlsCachePath);
            foreach ($directories as $dir) {
                $hash = basename($dir);
                $this->stopAndRemoveCache($hash);
            }
        }
        return redirect()->route('admin.hls.index');
    }

    private function stopAndRemoveCache($hash)
    {
        $cacheDir = $this->hlsCachePath . '/' . $hash;
        if (File::exists($cacheDir)) {
            $pidFile = $cacheDir . '/ffmpeg.pid';
            if (File::exists($pidFile)) {
                $pid = trim(File::get($pidFile));
                if (is_numeric($pid)) {
                    // Kill the process
                    shell_exec("kill -9 $pid > /dev/null 2>&1");
                }
            }
            File::deleteDirectory($cacheDir);
        }
    }
}
