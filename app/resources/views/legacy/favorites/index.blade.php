@extends('legacy.layout')
@section('title', 'お気に入り')
@section('content')
    <ul class="items">
        @forelse ($items as $item)
            <li>
                <a class="item-link" href="/{{ $item['type'] === 'folder' ? 'videos' : 'watch' }}/{{ implode('/', array_map('rawurlencode', explode('/', $item['path']))) }}">{{ $item['type'] === 'folder' ? 'フォルダ: ' : '' }}{{ $item['name'] }}</a>
                @if ($item['type'] === 'file')
                    <p class="details">{{ $item['is_cached'] ? '変換済み / ' : '' }}再生位置 {{ gmdate('H:i:s', $item['last_position']) }}</p>
                @endif
                <form method="post" action="/favorites/toggle">
                    @csrf<input type="hidden" name="path" value="{{ $item['path'] }}"><input type="hidden" name="type" value="{{ $item['type'] }}">
                    <button type="submit">お気に入り解除</button>
                </form>
            </li>
        @empty
            <li>お気に入りはまだありません。<a href="/videos">動画一覧を開く</a></li>
        @endforelse
    </ul>
@endsection
