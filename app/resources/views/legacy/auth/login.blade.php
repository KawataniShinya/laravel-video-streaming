@extends('legacy.layout')
@section('title', 'ログイン')
@section('content')
    {{-- Embedded browsers may block invalid input without showing their validation popup. --}}
    <form method="post" action="/login" class="login" novalidate>
        @csrf
        <label for="email">メールアドレス</label>
        <input id="email" name="email" type="email" value="{{ $email }}" required {{ $email === '' || $errors->has('email') ? 'autofocus' : '' }} autocomplete="username" @if($errors->has('email')) aria-invalid="true" @endif>
        <label for="password">パスワード</label>
        <input id="password" name="password" type="password" required {{ $email !== '' && !$errors->has('email') ? 'autofocus' : '' }} autocomplete="current-password" @if($errors->has('password')) aria-invalid="true" @endif>
        <p><label><input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}> ログイン状態を保持する</label></p>
        <button type="submit">ログイン</button>
    </form>
@endsection
