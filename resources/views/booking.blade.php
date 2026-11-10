@extends('layouts.app')
@section('title', 'Book your appointment — Chair & Blade')
@section('content')
@php
    $oldTime = is_string(old('time')) && in_array(old('time'), $slots, true) ? old('time') : '';
@endphp
<div class="reservation wrap">
    <div class="reservation-heading"><div><p class="eyebrow">A LITTLE TIME, JUST FOR YOU</p><h1>Your next <em>fresh start.</em></h1></div><p class="quiet">Your service. Your day. Your chair.<br>Choose a time and we’ll save your spot.</p></div>

    @if($errors->any())
        <div class="error-summary" role="alert" tabindex="-1" id="booking-errors"><strong>Let’s check a few details.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div id="booking-workspace">
        <section aria-labelledby="service-heading" class="service-picker">
            <h2 class="step-heading" id="service-heading"><span>01</span> Choose your service</h2>
            <div class="service-options">
                @forelse($services as $service)
                    <a class="service-option {{ $selectedService?->id === $service->id ? 'is-selected' : '' }}" href="{{ route('booking.create', ['service' => $service->id, 'month' => $month->format('Y-m'), 'date' => $selectedDate]) }}#booking-workspace" data-availability-link @if($selectedService?->id === $service->id) aria-current="true" @endif>
                        <span class="service-option-name">{{ $service->name }} <span class="selection-circle" aria-hidden="true">{{ $selectedService?->id === $service->id ? '✓' : '' }}</span></span>
                        <span class="service-option-meta">{{ $service->duration_minutes }} minutes <span>€{{ number_format($service->price_cents / 100, 2) }}</span></span>
                    </a>
                @empty
                    <p class="empty-state">There are no services to book right now. Please check back soon.</p>
                @endforelse
            </div>
        </section>

        @if($selectedService)
        <div class="reservation-grid">
            <section class="calendar-panel" aria-labelledby="calendar-heading">
                <h2 class="step-heading" id="calendar-heading"><span>02</span> Find your day</h2>
                <div class="calendar-toolbar">
                    @if($previousMonth)<a href="{{ route('booking.create', ['service' => $selectedService->id, 'month' => $previousMonth]) }}#booking-workspace" data-availability-link class="month-arrow" aria-label="Previous month">←</a>@else<span class="month-arrow is-disabled" aria-hidden="true">←</span>@endif
                    <h3 id="month-heading">{{ $month->format('F Y') }}</h3>
                    @if($nextMonth)<a href="{{ route('booking.create', ['service' => $selectedService->id, 'month' => $nextMonth]) }}#booking-workspace" data-availability-link class="month-arrow" aria-label="Next month">→</a>@else<span class="month-arrow is-disabled" aria-hidden="true">→</span>@endif
                </div>
                <div class="calendar-weekdays" aria-hidden="true">@foreach(['M', 'T', 'W', 'T', 'F', 'S', 'S'] as $weekday)<span>{{ $weekday }}</span>@endforeach</div>
                <div class="calendar-days" aria-labelledby="month-heading">
                    @foreach($calendarDays as $day)
                        @if($day['inMonth'] && $day['available'])
                            <a class="calendar-day is-available {{ $selectedDate === $day['date'] ? 'is-selected' : '' }}" href="{{ route('booking.create', ['service' => $selectedService->id, 'month' => $month->format('Y-m'), 'date' => $day['date']]) }}#time-heading" data-availability-link aria-label="{{ \Carbon\CarbonImmutable::parse($day['date'])->format('l, j F') }}, {{ $day['slotCount'] }} available times" @if($selectedDate === $day['date']) aria-current="date" @endif><span>{{ $day['day'] }}</span><span class="availability-dot" aria-hidden="true"></span></a>
                        @else
                            <span class="calendar-day is-unavailable {{ !$day['inMonth'] ? 'outside-month' : '' }}" aria-label="{{ \Carbon\CarbonImmutable::parse($day['date'])->format('l, j F') }}, unavailable"><span aria-hidden="true">{{ $day['day'] }}</span></span>
                        @endif
                    @endforeach
                </div>
                <div class="calendar-key"><span><i class="key-dot available" aria-hidden="true"></i> Available</span><span><i class="key-dot selected" aria-hidden="true"></i> Selected</span><span><i class="key-dot unavailable" aria-hidden="true"></i> Unavailable</span></div>
                <div class="calendar-note"><strong>Good things take a little time.</strong><p>We’ll reserve {{ $selectedService->duration_minutes }} minutes for this service.</p></div>
                <div class="schedule-note"><p>Tuesday–Saturday · 09:00–18:00<br>Lunch break · 13:00–14:00</p><p>All times in Malta ({{ $timezone }}).<br>Book 45 minutes to 60 days ahead.</p></div>
            </section>

            <section class="appointment-panel" aria-labelledby="time-heading">
                <form action="{{ route('booking.store') }}" method="POST" id="appointment-form">
                    @csrf
                    <input type="hidden" name="service_id" value="{{ $selectedService->id }}">
                    <input type="hidden" name="date" value="{{ $selectedDate }}">
                    <fieldset class="time-fieldset">
                        <legend class="step-heading" id="time-heading" tabindex="-1"><span>03</span> Pick your time</legend>
                        <p class="selected-date">{{ $selectedDate ? \Carbon\CarbonImmutable::parse($selectedDate)->format('l, j F') : 'Choose an available day' }} <span>Malta time</span></p>
                        @if(count($slots))
                        <div class="time-options">
                            @foreach($slots as $slot)
                            <label class="time-option"><input type="radio" name="time" value="{{ $slot }}" required @checked($oldTime === $slot) @error('time') aria-invalid="true" aria-describedby="time-error" @enderror><span>{{ $slot }}</span></label>
                            @endforeach
                        </div>
                        @else
                            <p class="empty-state">No times available{{ $selectedDate ? ' on this day' : ' this month' }}. Try another day or month on the calendar.</p>
                        @endif
                        @error('time')<p class="field-error" id="time-error">{{ $message }}</p>@enderror
                    </fieldset>
                    <fieldset class="contact-fields" @disabled(!count($slots))>
                        <legend class="step-heading"><span>04</span> Make it yours</legend>
                        @auth
                            <p class="quiet contact-intro">Booking as <strong>{{ auth()->user()->name }}</strong><br>{{ auth()->user()->email }}</p>
                            <p class="quiet contact-intro">Pay securely to confirm your time. Your appointment will appear in your account.</p>
                            @if(! $paymentsReady)<div class="notice">Online payment is temporarily unavailable. Please try again later.</div>@endif
                        @else
                            <p class="quiet contact-intro">Sign in or create an account to reserve your chair, pay securely, and receive appointment reminders.</p>
                        @endauth
                        <div class="appointment-summary"><div><strong>{{ $selectedService->name }}</strong><span>{{ $selectedService->duration_minutes }} min <span data-selected-time>{{ $oldTime ? '· '.$oldTime : '· Choose a time above' }}</span></span></div><strong>€{{ number_format($selectedService->price_cents / 100, 2) }}</strong></div>
                        @auth
                            <button class="button" type="submit" @disabled(!count($slots) || ! $paymentsReady)>Continue to secure payment <span aria-hidden="true">↗</span></button>
                            <p class="form-note quiet">Your time is held for 35 minutes during checkout.<br>{{ config('payments.mode') === 'test' ? 'Test payments only — use a Stripe test card.' : 'Pay by card with Stripe.' }}</p>
                        @else
                            <a class="button" href="{{ route('login') }}">Sign in to book <span aria-hidden="true">↗</span></a>
                            <p class="form-note quiet">New here? <a class="text-link" href="{{ route('register') }}">Create an account</a></p>
                        @endauth
                    </fieldset>
                </form>
            </section>
        </div>
        @endif
    </div>
    <p class="availability-status sr-only" role="status" aria-live="polite" id="availability-status"></p>
</div>
@endsection
