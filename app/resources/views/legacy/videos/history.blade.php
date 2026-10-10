@extends('legacy.layout')
@section('title', '視聴履歴')
@section('content')
    <ul class="items">
        @forelse ($items as $item)
            <li>
                <a class="item-link" href="/watch/{{ implode('/', array_map('rawurlencode', explode('/', $item['path']))) }}">{{ $item['name'] }}</a>
                <p class="details">再生位置 {{ gmdate('H:i:s', $item['last_position']) }} / 最終視聴 {{ $item['updated_at'] }}</p>
            </li>
        @empty
            <li>視聴履歴はまだありません。<a href="/videos">動画一覧を開く</a></li>
        @endforelse
    </ul>
@endsection
