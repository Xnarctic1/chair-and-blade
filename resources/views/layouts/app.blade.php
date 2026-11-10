<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Meet Chair & Blade, an independent barbershop concept. Explore our services and book your next cut with our live availability calendar.">
    <title>@yield('title', 'Chair & Blade — A cut above the everyday')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<div class="demo-banner">A fictional barbershop · Portfolio demo</div>
<header class="site-header wrap">
    <a class="brand" href="{{ route('home') }}" aria-label="Chair and Blade homepage">C<span>&</span>B<span class="brand-caption">CHAIR & BLADE</span></a>
    <nav aria-label="Main navigation">
        <a class="nav-home" href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif>The shop</a>
        @auth
            <a class="nav-account" href="{{ route('account.index') }}">My account</a>
            @else
            <a class="nav-account" href="{{ route('login') }}">Sign in</a>
            @endauth
        <a class="button small" href="{{ route('booking.create') }}" @if(request()->routeIs('booking.*')) aria-current="page" @endif>Book a chair <span aria-hidden="true">↗</span></a>
    </nav>
</header>
@auth
<div class="account-bar wrap"><span>{{ auth()->user()->name }}</span><div>@if(auth()->user()->is_admin)<a href="{{ route('admin.appointments.index') }}">Admin bookings</a>@endif<form action="{{ route('logout') }}" method="POST">@csrf<button type="submit">Sign out</button></form></div></div>
@endauth
<main id="main">@yield('content')</main>
<footer class="wrap">
    <a class="footer-brand" href="{{ route('home') }}">CHAIR & BLADE</a>
    <p>Good hair. Good company.</p>
    <span>Portfolio demo · No real-world appointments</span>
</footer>
</body>
</html>
