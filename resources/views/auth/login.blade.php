@extends('kaveh::layouts.app')
@section('title', 'Login — Kaveh')
@section('content')
<div class="panel">
    <h1>Kaveh</h1>
    <p style="color:var(--muted)">Self-hosted Laravel monitoring</p>
    <form method="post" action="{{ route('kaveh.login') }}">
        @csrf
        <div class="field">
            <label>Email</label>
            <input type="email" name="email" value="{{ old('email') }}" required style="width:100%">
        </div>
        <div class="field">
            <label>Password</label>
            <input type="password" name="password" required style="width:100%">
        </div>
        @error('email')<p style="color:var(--danger)">{{ $message }}</p>@enderror
        <button type="submit">Sign in</button>
        <p style="margin-top:1rem"><a href="{{ route('kaveh.register') }}">Create an account</a></p>
    </form>
</div>
@endsection
