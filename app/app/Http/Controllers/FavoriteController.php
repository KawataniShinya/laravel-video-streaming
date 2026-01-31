<?php

namespace App\Http\Controllers;

use App\Models\Favorite;
use App\Models\VideoView;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Illuminate\Support\Facades\File;

class FavoriteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $favorites = Favorite::where('user_id', Auth::id())->get();
        $items = [];

        // HLS cache path for checking status (optional, but good for consistency)
        $hlsCachePath = storage_path('hls');

        // Prepare watched status map for consistency
        $watchedVideos = VideoView::where('user_id', Auth::id())
            ->pluck('video_path')
            ->flip()
            ->toArray();

        foreach ($favorites as $fav) {
            $ext = pathinfo($fav->path, PATHINFO_EXTENSION);
            $name = basename($fav->path);
            
            $isCached = false;
            if ($fav->type === 'file' && in_array(strtolower($ext), ['m2ts', 'avi', 'flv', 'vob'])) {
                $hash = md5($fav->path);
                $playlist = $hlsCachePath . '/' . $hash . '/index.m3u8';
                $isCached = File::exists($playlist);
            }

            $items[] = [
                'type' => $fav->type, // 'file' or 'folder'
                'name' => $name,
                'path' => $fav->path,
                'ext' => strtolower($ext),
                'is_cached' => $isCached,
                'is_watched' => isset($watchedVideos[$fav->path]),
                'is_favorited' => true,
            ];
        }

        return Inertia::render('Favorites/Index', [
            'items' => $items,
        ]);
    }

    /**
     * Toggle favorite status.
     */
    public function toggle(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
            'type' => 'required|in:file,folder',
        ]);

        $userId = Auth::id();
        $path = $request->path;
        $type = $request->type;

        $favorite = Favorite::where('user_id', $userId)
            ->where('path', $path)
            ->first();

        if ($favorite) {
            $favorite->delete();
        } else {
            Favorite::create([
                'user_id' => $userId,
                'path' => $path,
                'type' => $type,
            ]);
        }

        return redirect()->back();
    }
}
