@extends('legacy.layout')
@section('title', 'ホーム')
@section('content')
    <p>{{ auth()->user()->name }} さん</p>
    <ul class="items">
        <li><a href="/videos">動画一覧を開く</a></li>
        <li><a href="/favorites">お気に入りを開く</a></li>
        <li><a href="/history">視聴履歴を開く</a></li>
    </ul>
@endsection
