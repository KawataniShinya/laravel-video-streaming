<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>
        <link rel="icon" type="image/svg+xml" href="/favicon.svg">
        <link rel="alternate icon" href="/favicon.ico">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
        <div id="legacy-fallback" data-forced-modern="{{ request()->cookie('ui_mode') === 'modern' ? '1' : '0' }}" style="display:none; padding:20px; background:white; color:black;">
            <p>画面が表示されない場合は、簡易表示をお試しください。</p>
            <a href="{{ route('ui.mode', ['mode' => 'legacy', 'return' => request()->getRequestUri()], false) }}">簡易表示に切り替える</a>
        </div>
        <noscript><a href="{{ route('ui.mode', ['mode' => 'legacy', 'return' => request()->getRequestUri()], false) }}">簡易表示に切り替える</a></noscript>
        <script src="/legacy/detect.js"></script>
    </body>
</html>
