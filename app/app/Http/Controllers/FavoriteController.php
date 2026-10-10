<?php

namespace App\Http\Controllers;

use App\UseCase\FavoriteUseCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Services\PageRenderer;

class FavoriteController extends Controller
{
    public function __construct(
        private readonly FavoriteUseCase $favoriteUseCase,
        private readonly PageRenderer $pages,
    ) {
    }

    public function index()
    {
        $favorites = $this->favoriteUseCase->list(Auth::user());

        return $this->pages->render(request(), 'Favorites/Index', $favorites->jsonSerialize());
    }

    public function toggle(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
            'type' => 'required|in:file,folder',
        ]);

        $this->favoriteUseCase->toggle(Auth::user(), $request->path, $request->type);

        return redirect()->back();
    }
}
