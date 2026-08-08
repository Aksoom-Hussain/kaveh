@extends('kaveh::layouts.app')
@section('title', 'Register — Kaveh')
@section('content')
<div class="panel">
    <h1>Create account</h1>
    <form method="post" action="{{ route('kaveh.register') }}">
        @csrf
        <div class="field"><label>Name</label><input name="name" value="{{ old('name') }}" required style="width:100%"></div>
        <div class="field"><label>Email</label><input type="email" name="email" value="{{ old('email') }}" required style="width:100%"></div>
        <div class="field"><label>Organization</label><input name="organization" value="{{ old('organization') }}" required style="width:100%"></div>
        <div class="field"><label>Password</label><input type="password" name="password" required style="width:100%"></div>
        <div class="field"><label>Confirm password</label><input type="password" name="password_confirmation" required style="width:100%"></div>
        @foreach($errors->all() as $error)<p style="color:var(--danger)">{{ $error }}</p>@endforeach
        <button type="submit">Register</button>
    </form>
</div>
@endsection
