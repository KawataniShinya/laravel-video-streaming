<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', '動画ライブラリ') - {{ config('app.name') }}</title>
    <link rel="icon" href="/favicon.ico">
    <link rel="stylesheet" href="/legacy/style.css">
</head>
<body>
    <header>
        <a href="{{ auth()->check() ? '/dashboard' : '/login' }}">{{ config('app.name') }}</a>
        <span class="badge">簡易表示</span>
        @auth
            <nav aria-label="メインメニュー">
                <a href="/dashboard">ホーム</a>
                <a href="/videos">動画一覧</a>
                <a href="/favorites">お気に入り</a>
                <a href="/history">視聴履歴</a>
                <form method="post" action="/logout" class="inline">@csrf<button type="submit">ログアウト</button></form>
            </nav>
        @endauth
    </header>
    <main>
        <h1>@yield('title', '動画ライブラリ')</h1>
        @if (session('status'))<p class="notice" role="status">{{ session('status') }}</p>@endif
        @if (session('error'))<p class="error" role="alert">{{ session('error') }}</p>@endif
        @if ($errors->any())
            <div class="error" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        @yield('content')
    </main>
    <footer>
        <a href="{{ route('ui.mode', ['mode' => 'modern', 'return' => request()->getRequestUri()], false) }}">通常表示に切り替える</a>
        <a href="{{ route('ui.mode', ['mode' => 'auto', 'return' => request()->getRequestUri()], false) }}">表示方式を自動判定に戻す</a>
    </footer>
    @yield('scripts')
</body>
</html>
