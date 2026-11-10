@extends('layouts.app')
@section('title', 'Choose a new password — Chair & Blade')
@section('content')
<section class="account-page wrap"><div class="auth-panel"><p class="eyebrow">BACK TO YOUR ACCOUNT</p><h1>Make it <em>new.</em></h1><p class="quiet">Choose a new password with at least 12 characters.</p>
    @include('auth.feedback')
    <form action="{{ route('password.update') }}" method="POST">@csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <label for="email">Email address</label><input id="email" name="email" type="email" required autocomplete="email" maxlength="255" value="{{ is_string(old('email', $email)) ? old('email', $email) : '' }}" @error('email') aria-invalid="true" @enderror>
        <label for="password">New password</label><input id="password" name="password" type="password" required minlength="12" autocomplete="new-password" @error('password') aria-invalid="true" @enderror>
        <label for="password_confirmation">Confirm new password</label><input id="password_confirmation" name="password_confirmation" type="password" required minlength="12" autocomplete="new-password">
        <button class="button" type="submit">Reset password <span aria-hidden="true">↗</span></button>
    </form>
</div></section>
@endsection
