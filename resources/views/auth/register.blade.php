@extends('layouts.app')
@section('title', 'Create an account — Chair & Blade')
@section('content')
<section class="account-page wrap">
    <div class="auth-panel"><p class="eyebrow">MAKE YOURSELF AT HOME</p><h1>Your next <em>chapter.</em></h1><p class="quiet">Create an account to book, pay securely, and keep your appointments in one place.</p>
        @include('auth.feedback')
        <form action="{{ route('register.store') }}" method="POST">@csrf
            <label for="name">Your name</label><input id="name" name="name" required autocomplete="name" maxlength="100" value="{{ is_string(old('name')) ? old('name') : '' }}" @error('name') aria-invalid="true" @enderror>
            <label for="email">Email address</label><input id="email" name="email" type="email" required autocomplete="email" maxlength="255" value="{{ is_string(old('email')) ? old('email') : '' }}" @error('email') aria-invalid="true" @enderror>
            <label for="password">Password</label><input id="password" name="password" type="password" required minlength="12" autocomplete="new-password" aria-describedby="password-help" @error('password') aria-invalid="true" @enderror><small id="password-help">Use at least 12 characters.</small>
            <label for="password_confirmation">Confirm password</label><input id="password_confirmation" name="password_confirmation" type="password" required minlength="12" autocomplete="new-password">
            <button class="button" type="submit">Create my account <span aria-hidden="true">↗</span></button>
        </form>
        <p class="quiet auth-links">Already have an account? <a class="text-link" href="{{ route('login') }}">Sign in</a></p>
    </div>
</section>
@endsection
