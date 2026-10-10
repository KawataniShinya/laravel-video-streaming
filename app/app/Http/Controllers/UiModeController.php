<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Inertia\Inertia;

class UiModeController extends Controller
{
    public function __invoke(Request $request, string $mode)
    {
        abort_unless(in_array($mode, ['legacy', 'modern', 'auto'], true), 404);
        if ($mode === 'auto') {
            Cookie::queue(Cookie::forget('ui_mode'));
        } else {
            Cookie::queue(cookie('ui_mode', $mode, 60 * 24 * 30, '/', null, $request->isSecure(), true, false, 'lax'));
        }

        $target = $request->query('return', '/');
        // Accept only local absolute paths, including after URL decoding.
        $decoded = is_string($target) ? rawurldecode($target) : '';
        if (!str_starts_with($decoded, '/') || str_starts_with($decoded, '//') || preg_match('/[\\\\\x00-\x20\x7f]/', $decoded)) {
            $target = '/';
        }

        return $request->header('X-Inertia') ? Inertia::location($target) : redirect($target);
    }
}
