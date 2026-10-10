@extends('legacy.layout')
@section('title', $filename)
@section('content')
    <p><a href="/videos/{{ implode('/', array_map('rawurlencode', explode('/', dirname($path) === '.' ? '' : dirname($path)))) }}">動画のフォルダに戻る</a></p>
    <video id="legacy-video" controls preload="none"
        data-kind="{{ isset($hash) ? 'hls' : 'mp4' }}"
        data-source="{{ isset($hash) ? '/hls/' . $hash . '/index.m3u8' : '/stream/' . implode('/', array_map('rawurlencode', explode('/', $path))) }}"
        data-path="{{ $path }}" data-position="{{ $lastPosition }}">
    </video>
    <p>
        <button type="button" id="play">再生</button>
        <button type="button" id="pause">一時停止</button>
        <button type="button" id="rewind">30秒戻す</button>
        <button type="button" id="forward">30秒進める</button>
        <button type="button" id="fullscreen">全画面</button>
    </p>
    @if (isset($hash))
        <p>
            <label for="quality">画質</label><select id="quality"><option value="-1">自動</option></select>
            <label for="audio">音声</label><select id="audio"><option value="-1">既定</option></select>
        </p>
    @endif
    <p id="playback-status" role="status">再生ボタンを押してください。前回の再生位置: {{ gmdate('H:i:s', $lastPosition) }}</p>
    <p id="progress-status" role="status"></p>
    <noscript>再生にはJavaScriptを有効にしてください。</noscript>
    <form method="post" action="/favorites/toggle">
        @csrf<input type="hidden" name="path" value="{{ $path }}"><input type="hidden" name="type" value="file">
        <button type="submit">{{ $isFavorited ? 'お気に入り解除' : 'お気に入り登録' }}</button>
    </form>
@endsection
@section('scripts')
    @if (isset($hash))<script src="/legacy/hls.min.js"></script>@endif
    <script src="/legacy/player.js?v={{ filemtime(public_path('legacy/player.js')) }}"></script>
@endsection
