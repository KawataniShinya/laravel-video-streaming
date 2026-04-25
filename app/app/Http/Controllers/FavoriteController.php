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

        $watchedVideoIds = VideoView::where('user_id', Auth::id())
            ->pluck('video_id')
            ->flip()
            ->toArray();

        foreach ($favorites as $fav) {
            $video = $fav->video;
            if (!$video) continue;

            $ext = pathinfo($video->path, PATHINFO_EXTENSION);
            $name = basename($video->path);
            
            $isCached = false;
            if ($video->type === 'file' && in_array(strtolower($ext), ['m2ts', 'avi', 'flv', 'vob'])) {
                $playlist = $hlsCachePath . '/' . $video->hash . '/index.m3u8';
                $isCached = File::exists($playlist);
            }

            $items[] = [
                'id' => $video->id,
                'type' => $video->type,
                'name' => $name,
                'path' => $video->path,
                'ext' => strtolower($ext),
                'is_cached' => $isCached,
                'is_watched' => isset($watchedVideoIds[$video->id]),
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
}
