<?php

namespace App\Http\Controllers;

use App\UseCase\AdminHlsCacheUseCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class HlsCacheController extends Controller
{
    public function __construct(
        private readonly AdminHlsCacheUseCase $adminHlsCacheUseCase,
    ) {
    }

    public function index()
    {
        $caches = $this->adminHlsCacheUseCase->list(Auth::user());

        return Inertia::render('Admin/HlsCache/Index', $caches->jsonSerialize());
    }

    public function getSize($hash)
    {
        $size = $this->adminHlsCacheUseCase->size(Auth::user(), $hash);

        return response()->json($size->jsonSerialize());
    }

    public function destroy($hash)
    {
        $this->adminHlsCacheUseCase->delete(Auth::user(), $hash);

        return redirect()->route('admin.hls.index');
    }

    public function destroyMultiple(Request $request)
    {
        $hashes = $request->input('hashes', []);
        $this->adminHlsCacheUseCase->deleteMultiple(Auth::user(), $hashes);

        return redirect()->route('admin.hls.index');
    }

    public function destroyAll()
    {
        $this->adminHlsCacheUseCase->deleteAll(Auth::user());

        return redirect()->route('admin.hls.index');
    }
}
