<?php

namespace App\Services;

use Illuminate\Http\Request;
use Inertia\Inertia;

class PageRenderer
{
    private const LEGACY_VIEWS = [
        'Auth/Login' => 'legacy.auth.login',
        'Dashboard' => 'legacy.dashboard',
        'Videos/Index' => 'legacy.videos.index',
        'Videos/WatchMp4' => 'legacy.videos.watch',
        'Videos/WatchHls' => 'legacy.videos.watch',
        'Favorites/Index' => 'legacy.favorites.index',
        'Videos/History' => 'legacy.videos.history',
    ];

    public function render(Request $request, string $component, array $data = [])
    {
        if ($request->attributes->get('legacy_ui') && isset(self::LEGACY_VIEWS[$component])) {
            // An Inertia visit must become a full document navigation before serving Blade.
            if ($request->header('X-Inertia')) {
                return Inertia::location($request->fullUrl());
            }

            $data = json_decode(json_encode($data, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);

            return response()->view(self::LEGACY_VIEWS[$component], $data + ['component' => $component]);
        }

        return Inertia::render($component, $data);
    }
}
