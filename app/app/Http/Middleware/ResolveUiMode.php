<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

class ResolveUiMode
{
    public function handle(Request $request, Closure $next): Response
    {
        $mode = $request->cookie('ui_mode');
        $legacy = $mode === 'legacy' || ($mode !== 'modern' && $this->isOldBrowser($request->userAgent() ?? ''));
        $request->attributes->set('legacy_ui', $legacy);
        $request->attributes->set('ui_mode_explicit', in_array($mode, ['legacy', 'modern'], true));
        if ($legacy && !in_array($mode, ['legacy', 'modern'], true)) {
            Cookie::queue(cookie('ui_mode', 'legacy', 60 * 24 * 30, '/', null, $request->isSecure(), true, false, 'lax'));
        }

        // These screens are outside the seven-template legacy interface.
        if ($legacy && $request->isMethod('GET') && ($request->is('admin/*', 'profile', 'register', 'forgot-password', 'reset-password/*', 'verify-email', 'confirm-password'))) {
            return redirect($request->user() ? '/dashboard' : '/login')
                ->with('status', 'この画面は通常表示で利用できます。必要な場合は通常表示に切り替えてください。');
        }

        $response = $next($request);
        if ($request->isMethod('GET') && str_contains($response->headers->get('Content-Type', ''), 'text/html')) {
            $response->setVary(['Cookie', 'User-Agent'], false);
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }

    private function isOldBrowser(string $ua): bool
    {
        if (str_contains($ua, 'PASTB') || preg_match('/MSIE|Trident\//', $ua)) {
            return true;
        }
        foreach (['/(?:Chrome|Chromium)\/(\d+)/' => 87, '/Firefox\/(\d+)/' => 78, '/Version\/(\d+).*Safari\//' => 14] as $pattern => $minimum) {
            if (preg_match($pattern, $ua, $matches)) {
                return (int) $matches[1] < $minimum;
            }
        }

        return false;
    }
}
