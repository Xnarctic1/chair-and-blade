@extends('layouts.app')
@section('content')
<section class="hero wrap">
    <div class="hero-copy">
        <p class="eyebrow"><span class="dot"></span> YOUR NEIGHBOURHOOD BARBERSHOP</p>
        <h1>A cut above<br>the <em>everyday.</em></h1>
        <p class="intro">Good conversation. Considered cuts. A little time for yourself. Pull up a chair — we’ll take care of the rest.</p>
        <a class="button" href="{{ route('booking.create') }}">Find your time <span aria-hidden="true">↗</span></a>
        <p class="quiet hero-note">Precision cuts &nbsp; / &nbsp; Beard care &nbsp; / &nbsp; No rush</p>
    </div>
    <div class="hero-art" role="img" aria-label="Graphic illustration of a traditional barber pole inside an arched shop window">
        <span class="art-top">THE ART OF LOOKING SHARP</span>
        <div class="arch"><div class="pole"><div class="pole-cap"></div><div class="pole-stripes"></div><div class="pole-cap bottom"></div></div><span class="arch-letter">C&B</span></div>
        <div class="art-bottom"><span>CLASSIC CRAFT.<br>FRESH PERSPECTIVE.</span><span class="seal">TAKE<br>YOUR<br>TIME ↗</span></div>
    </div>
</section>
<div class="ticker" aria-hidden="true">A GOOD CUT GOES A LONG WAY <span>✳</span> MADE FOR YOUR EVERYDAY <span>✳</span> YOUR CHAIR IS WAITING <span>✳</span></div>
<section class="story introduction wrap" id="story">
    <div><p class="eyebrow">01 / WELCOME TO THE SHOP</p><h2>A familiar chair.<br>A fresh perspective.</h2></div>
    <div><p>Chair & Blade is built around a simple idea: a great haircut should feel as good as it looks. A warm welcome, a proper consultation, and the time to get every detail right.</p><p class="quiet">From your everyday cut to a new direction, we work with your hair, your routine, and your own sense of style. Classic craft, without the fuss.</p><a class="text-link" href="#services">Find your favourite service <span aria-hidden="true">↓</span></a></div>
</section>
<section class="services wrap" id="services">
    <div class="section-head"><div><p class="eyebrow">02 / THE MENU</p><h2>Keep it sharp.<br>Keep it simple.</h2></div><p>Thoughtful services. Straightforward prices.<br>Find the ritual that fits your day.</p></div>
    <div class="service-grid">
        @foreach($services as $service)
        <article class="service-card">
            <div class="service-top"><span>0{{ $loop->iteration }}</span><span>{{ $service->duration_minutes }} MIN</span></div>
            <h3>{{ $service->name }}</h3><p>{{ $service->description }}</p>
            <div class="service-bottom"><span class="price">€{{ number_format($service->price_cents / 100, 2) }}</span><a href="{{ route('booking.create', ['service' => $service->id]) }}">Find a time <span aria-hidden="true">↗</span><span class="sr-only">: {{ $service->name }}</span></a></div>
        </article>
        @endforeach
    </div>
</section>
<section class="visit wrap" aria-labelledby="visit-heading">
    <div><p class="eyebrow">03 / YOUR CHAIR IS WAITING</p><h2 id="visit-heading">A little time.<br>A big difference.</h2><p>Choose a service, find a free spot on the calendar, and make it yours.</p><a class="button" href="{{ route('booking.create') }}">See availability <span aria-hidden="true">↗</span></a></div>
    <div class="hours"><h3>Time for a fresh start</h3><dl><div><dt>Tuesday – Saturday</dt><dd>09:00 – 18:00</dd></div><div><dt>A pause for lunch</dt><dd>13:00 – 14:00</dd></div><div><dt>Sunday & Monday</dt><dd>Closed</dd></div></dl><p>All times are in Malta time. Book up to 60 days ahead.</p></div>
</section>
@endsection
