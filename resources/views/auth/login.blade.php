@extends('layouts.app')
@section('title', 'Sign in — Chair & Blade')
@section('content')
<section class="account-page wrap">
    <div class="auth-panel"><p class="eyebrow">YOUR CHAIR, YOUR ACCOUNT</p><h1>Welcome <em>back.</em></h1><p class="quiet">Sign in to book your next visit and see your appointments.</p>
        @include('auth.feedback')
        <form action="{{ route('login.store') }}" method="POST">@csrf
            <label for="email">Email address</label><input type="email" id="email" name="email" required autocomplete="email" value="{{ is_string(old('email')) ? old('email') : '' }}" maxlength="255" @error('email') aria-invalid="true" @enderror>
            <label for="password">Password</label><input type="password" id="password" name="password" required autocomplete="current-password" @error('password') aria-invalid="true" @enderror>
            <label class="checkbox-label"><input type="checkbox" name="remember" value="1" @checked(old('remember') === '1')><span>Keep me signed in</span></label>
            <button class="button" type="submit">Sign in <span aria-hidden="true">↗</span></button>
        </form>
        <div class="auth-links"><a class="text-link" href="{{ route('password.request') }}">Forgot your password?</a><p class="quiet">First time here? <a class="text-link" href="{{ route('register') }}">Create an account</a></p></div>
    </div>
</section>
@endsection
