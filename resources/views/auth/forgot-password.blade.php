@extends('layouts.app')
@section('title', 'Reset your password — Chair & Blade')
@section('content')
<section class="account-page wrap"><div class="auth-panel"><p class="eyebrow">LET’S GET YOU BACK IN</p><h1>A fresh <em>start.</em></h1><p class="quiet">Enter the email you used to register and we’ll send a password reset link.</p>
    @include('auth.feedback')
    <form action="{{ route('password.email') }}" method="POST">@csrf
        <label for="email">Email address</label><input id="email" name="email" type="email" required autocomplete="email" maxlength="255" value="{{ is_string(old('email')) ? old('email') : '' }}" @error('email') aria-invalid="true" @enderror>
        <button class="button" type="submit">Send reset link <span aria-hidden="true">↗</span></button>
    </form><p class="auth-links"><a class="text-link" href="{{ route('login') }}">← Back to sign in</a></p>
</div></section>
@endsection
