<?php

namespace App\Http\Controllers;

use App\Models\Favorite;
use App\Models\VideoView;
use App\Models\Video as VideoModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Illuminate\Support\Facades\File;

class FavoriteController extends Controller
{
    public function index()
    {
        $favorites = Favorite::with('video')->where('user_id', Auth::id())->get();
        $items = [];
        $hlsCachePath = config('video.hls_cache_path', storage_path('hls'));

        $videoViews = VideoView::where('user_id', Auth::id())
            ->get()
            ->keyBy('video_id');

        foreach ($favorites as $fav) {
            $video = $fav->video;
            if (!$video) continue;

            $ext = pathinfo($video->path, PATHINFO_EXTENSION);
            $name = basename($video->path);
            
            $isCached = false;
            if ($video->type === 'file' && in_array(strtolower($ext), ['m2ts', 'avi', 'flv', 'vob'])) {
                $outputDir = $hlsCachePath . '/' . $video->hash;
                $playlist = $outputDir . '/index.m3u8';
                $pidFile = $outputDir . '/ffmpeg.pid';
                
                $hasPlaylist = File::exists($playlist);
                $isRunning = false;
                
                if (File::exists($pidFile)) {
                    $pid = trim(File::get($pidFile));
                    if (is_numeric($pid)) {
                        $isRunning = $this->isProcessRunning($pid);
                    }
                }

                $isCached = $hasPlaylist && !$isRunning;
            }

            $view = $videoViews->get($video->id);

            $items[] = [
                'id' => $video->id,
                'type' => $video->type,
                'name' => $name,
                'path' => $video->path,
                'ext' => strtolower($ext),
                'is_cached' => $isCached,
                'is_watched' => (bool)$view,
                'last_position' => $view ? $view->last_position : 0,
                'is_favorited' => true,
            ];
        }

        return Inertia::render('Favorites/Index', [
            'items' => $items,
        ]);
    }

    public function toggle(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
            'type' => 'required|in:file,folder',
        ]);

        $path = rawurldecode($request->path);
        
        // Ensure video master record exists
        $video = VideoModel::firstOrCreate(
            ['path' => $path],
            ['hash' => md5($path), 'type' => $request->type]
        );

        $favorite = Favorite::where('user_id', Auth::id())
            ->where('video_id', $video->id)
            ->first();

        if ($favorite) {
            $favorite->delete();
        } else {
            Favorite::create([
                'user_id' => Auth::id(),
                'video_id' => $video->id,
                'type' => $request->type,
            ]);
        }

        return redirect()->back();
    }

    private function isProcessRunning($pid)
    {
        $output = shell_exec("ps -p $pid 2>/dev/null");
        return strpos($output, (string)$pid) !== false;
    }
}
