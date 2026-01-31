<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\VideoView;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;

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
        $size = 0;
        foreach (File::allFiles($path) as $file) {
            $size += $file->getSize();
        }
        return $size;
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
        $totalSize = 0;

        // Try to map hashes to filenames using VideoView history
        // This is a best-effort approach.
        $knownPaths = VideoView::distinct()->pluck('video_path')->toArray();
        $hashMap = [];
        foreach ($knownPaths as $path) {
            $hashMap[md5($path)] = $path;
        }

        if (File::exists($this->hlsCachePath)) {
            $directories = File::directories($this->hlsCachePath);
            foreach ($directories as $dir) {
                $hash = basename($dir);
                $size = $this->getDirectorySize($dir);
                $totalSize += $size;

                $caches[] = [
                    'hash' => $hash,
                    'path' => $hashMap[$hash] ?? 'Unknown (or deleted from history)',
                    'size' => $this->formatBytes($size),
                    'size_bytes' => $size,
                ];
            }
        }

        // Sort by size desc
        usort($caches, function ($a, $b) {
            return $b['size_bytes'] <=> $a['size_bytes'];
        });

        return Inertia::render('Admin/HlsCache/Index', [
            'caches' => $caches,
            'totalSize' => $this->formatBytes($totalSize),
        ]);
    }

    public function destroy($hash)
    {
        $this->authorizeAdmin();

        $dir = $this->hlsCachePath . '/' . $hash;
        if (File::exists($dir)) {
            File::deleteDirectory($dir);
        }

        return redirect()->back();
    }

    public function destroyAll()
    {
        $this->authorizeAdmin();

        if (File::exists($this->hlsCachePath)) {
            $directories = File::directories($this->hlsCachePath);
            foreach ($directories as $dir) {
                File::deleteDirectory($dir);
            }
        }

        return redirect()->back();
    }
}
