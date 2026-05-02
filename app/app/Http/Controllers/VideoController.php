<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Video\UserAccessService;
use App\Services\Video\VideoPathService;
use App\Services\VideoStream;
use App\UseCase\VideoUseCase;
use App\ValueObjects\Video\VideoPath;
use App\Enums\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;

class VideoController extends Controller
{
    public function __construct(
        private readonly VideoUseCase $videoUseCase,
        private readonly UserAccessService $accessService,
        private readonly VideoPathService $pathService,
    ) {
    }

    public function index($path = null)
    {
        $user = Auth::user();
        if (!$this->accessService->canAccessPath($user, $path ?? '')) {
            return redirect()->route('dashboard')->with('error', 'You do not have access to this folder.');
        }

        $fullPath = $this->pathService->resolvePath($path);
        if ($fullPath && File::isFile($fullPath)) {
            return redirect()->route('videos.watch', ['path' => $path]);
        }

        $library = $this->videoUseCase->list($user, $path);

        return Inertia::render('Videos/Index', $library->jsonSerialize());
    }

    public function history()
    {
        $history = $this->videoUseCase->history(Auth::user());

        return Inertia::render('Videos/History', $history->jsonSerialize());
    }

    public function watch($path)
    {
        $user = Auth::user();
        $path = rawurldecode($path);

        if (!$this->accessService->canAccessPath($user, $path)) {
            return redirect()->route('dashboard')->with('error', 'You do not have access to this video.');
        }

        $normalizedPath = $this->pathService->normalizePrimaryVobPath($path);
        if ($normalizedPath && $normalizedPath !== $path) {
            return redirect()->route('videos.watch', ['path' => $normalizedPath]);
        }

        $watch = $this->videoUseCase->watch($user, $path);
        $videoPath = new VideoPath($path);
        $props = $watch->jsonSerialize();

        if ($videoPath->extension() === 'mp4') {
            return Inertia::render('Videos/WatchMp4', $props);
        }

        return Inertia::render('Videos/WatchHls', $props);
    }

    public function stream($path)
    {
        $user = Auth::user();
        $path = rawurldecode($path);

        if (!$this->accessService->canAccessPath($user, $path)) {
            abort(403);
        }

        $fullPath = $this->pathService->resolvePath($path);
        if (!$fullPath || !File::isFile($fullPath)) {
            abort(404);
        }

        $stream = new VideoStream($fullPath);
        $stream->start();
    }

    public function updateProgress(Request $request)
    {
        $user = Auth::user();
        $path = rawurldecode($request->input('path'));
        $time = $request->input('time');

        if (!$this->accessService->canAccessPath($user, $path)) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        if (!$path || !is_numeric($time)) {
            return response()->json(['status' => 'error'], 400);
        }

        $this->videoUseCase->updateProgress($user, $path, (int) $time);

        return response()->json(['status' => 'success']);
    }

    public function serveHls($hash, $file)
    {
        $path = config('video.hls_cache_path', storage_path('hls')) . '/' . $hash . '/' . $file;
        if (!File::exists($path)) {
            abort(404);
        }

        return response()->file($path);
    }

    public function deleteCache(Request $request)
    {
        if (Auth::user()->role !== Role::Admin->value) {
            abort(403);
        }

        $path = $request->input('path');
        if (!$path) {
            return redirect()->route('videos.index');
        }

        $this->videoUseCase->deleteCache($path);

        return redirect()->route('videos.index', ['path' => $path ? dirname(rawurldecode($path)) : null]);
    }

    public function toggleWatchStatus(Request $request)
    {
        $path = $request->input('path');
        if (!$path) {
            return redirect()->back();
        }

        $this->videoUseCase->toggleWatched(Auth::user(), $path);

        return redirect()->back();
    }
}
