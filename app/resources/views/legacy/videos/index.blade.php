@extends('legacy.layout')
@section('title', '動画一覧')
@section('content')
    <nav aria-label="フォルダ階層"><a href="/videos">ライブラリ</a>
        @foreach ($breadcrumbs as $crumb)
            / <a href="/videos/{{ implode('/', array_map('rawurlencode', explode('/', $crumb['path']))) }}">{{ $crumb['name'] }}</a>
        @endforeach
    </nav>
    @if ($currentPath)
        <p><a href="/videos/{{ implode('/', array_map('rawurlencode', explode('/', dirname($currentPath) === '.' ? '' : dirname($currentPath)))) }}">上のフォルダに戻る</a></p>
    @endif
    <ul class="items">
        @forelse ($items as $item)
            <li>
                <a class="item-link" href="/{{ $item['type'] === 'folder' ? 'videos' : 'watch' }}/{{ implode('/', array_map('rawurlencode', explode('/', $item['path']))) }}">{{ $item['type'] === 'folder' ? 'フォルダ: ' : '' }}{{ $item['name'] }}</a>
                @if ($item['type'] === 'file')
                    <p class="details">{{ $item['size'] }} {{ $item['is_cached'] ? ' / 変換済み' : '' }} {{ $item['is_watched'] ? ' / 視聴済み' : '' }}</p>
                @endif
                <form method="post" action="/favorites/toggle" class="inline">
                    @csrf<input type="hidden" name="path" value="{{ $item['path'] }}"><input type="hidden" name="type" value="{{ $item['type'] }}">
                    <button type="submit">{{ $item['is_favorited'] ? 'お気に入り解除' : 'お気に入り登録' }}</button>
                </form>
                @if ($item['type'] === 'file')
                    <form method="post" action="/videos/watched/toggle" class="inline">
                        @csrf<input type="hidden" name="path" value="{{ $item['path'] }}">
                        <button type="submit">{{ $item['is_watched'] ? '未視聴にする' : '視聴済みにする' }}</button>
                    </form>
                @endif
            </li>
        @empty
            <li>動画・フォルダがありません。</li>
        @endforelse
    </ul>
@endsection
