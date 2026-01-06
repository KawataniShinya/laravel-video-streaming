<?php

namespace App\Http\Controllers;

use App\Services\VideoStream;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VideoController extends Controller
{
    private $videoPath = '/videos';
    private $hlsCachePath;

    public function __construct()
    {
        $this->hlsCachePath = storage_path('hls');
    }

    public function index()
    {
        $files = [];
        if (File::exists($this->videoPath)) {
            $allFiles = File::files($this->videoPath);
            foreach ($allFiles as $file) {
                $ext = strtolower($file->getExtension());
                if (in_array($ext, ['mp4', 'm2ts'])) {
                    $files[] = $file->getFilename();
                }
            }
        }

        return view('videos.index', compact('files'));
    }

    public function watch($filename)
    {
        $path = $this->videoPath . '/' . $filename;
        if (!File::exists($path)) {
            abort(404);
        }

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if ($ext === 'mp4') {
            // Check for faststart (simple check, or just assume)
            // For now, we just play it.
            return view('videos.watch_mp4', compact('filename'));
        } elseif ($ext === 'm2ts') {
            $this->ensureHls($filename);
            return view('videos.watch_hls', compact('filename'));
        }

        abort(404);
    }

    public function stream($filename)
    {
        $path = $this->videoPath . '/' . $filename;
        if (!File::exists($path)) {
            abort(404);
        }
        
        $stream = new VideoStream($path);
        $stream->start();
    }

    private function ensureHls($filename)
    {
        $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
        $outputDir = $this->hlsCachePath . '/' . $nameWithoutExt;
        $playlist = $outputDir . '/index.m3u8';

        if (!File::exists($outputDir)) {
            File::makeDirectory($outputDir, 0755, true);
        }

        if (!File::exists($playlist)) {
            // Start conversion in background
            // Using nohup to let it run in background
            $input = $this->videoPath . '/' . $filename;
            
            // Basic HLS conversion
            // -c:v libx264 -c:a aac: transcode to standard HLS compatible formats
            // -f hls: format
            // -hls_time 10: segment duration
            // -hls_list_size 0: keep all segments in playlist
            
            $cmd = "nohup ffmpeg -i " . escapeshellarg($input) . " -c:v libx264 -c:a aac -f hls -hls_time 10 -hls_list_size 0 " . escapeshellarg($playlist) . " > /dev/null 2>&1 &";
            
            Process::run($cmd);
        }
    }

    public function serveHls($filename, $file)
    {
        // $filename is the original m2ts filename (folder name in hls cache)
        // $file is index.m3u8 or segment.ts
        
        $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
        $path = $this->hlsCachePath . '/' . $nameWithoutExt . '/' . $file;

        if (!File::exists($path)) {
            // It might be converting. Return 404 or hold?
            // HLS player retries.
            abort(404);
        }

        return response()->file($path);
    }
}
