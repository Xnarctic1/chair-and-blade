@extends('layouts.app')
@section('title', 'Your visit — Chair & Blade')
@section('content')
<section class="account-page wrap">
    <a class="text-link" href="{{ route('account.index') }}">← Your appointments</a>
    <div class="account-card appointment-detail"><p class="eyebrow">APPOINTMENT {{ $appointment->reference }}</p><h1>{{ $appointment->service->name }}</h1>
        @include('auth.feedback')
        @if($appointment->status === 'confirmed')<div class="success" role="status">Your appointment is confirmed. We look forward to seeing you.</div>
        @elseif($appointment->status === 'pending_payment')<div class="notice"><strong>Your time is held while you pay.</strong><p>Complete payment to confirm your appointment. If you leave checkout, you can return here and try again.</p>@if($appointment->hold_expires_at)<p>Checkout expires at {{ $appointment->hold_expires_at->setTimezone(config('booking.timezone'))->format('H:i, j F') }} (Malta time).</p>@endif</div>
        @else<div class="notice">This appointment is {{ str_replace('_', ' ', $appointment->status) }}. <a class="text-link" href="{{ route('booking.create') }}">Find another time</a>.</div>@endif
        <dl class="appointment-facts"><div><dt>Date</dt><dd>{{ $appointment->starts_at->setTimezone(config('booking.timezone'))->format('l, j F Y') }}</dd></div><div><dt>Time</dt><dd>{{ $appointment->starts_at->setTimezone(config('booking.timezone'))->format('H:i') }}–{{ $appointment->ends_at->setTimezone(config('booking.timezone'))->format('H:i') }} · Malta time</dd></div><div><dt>Customer</dt><dd>{{ $appointment->name }}<small>{{ $appointment->email }}</small></dd></div><div><dt>Total</dt><dd>€{{ number_format($appointment->price_cents / 100, 2) }}</dd></div><div><dt>Payment</dt><dd>{{ ucfirst(str_replace('_', ' ', $appointment->payment_status ?? 'not_required')) }}</dd></div></dl>
        @if($appointment->status === 'pending_payment' && auth()->id() === $appointment->user_id && \Illuminate\Support\Facades\Route::has('payments.checkout'))
        <form action="{{ route('payments.checkout', $appointment) }}" method="POST">@csrf<button class="button" type="submit">Continue to secure payment <span aria-hidden="true">↗</span></button></form>
        @endif
        <p class="quiet">Questions about this appointment? Contact the shop with your booking reference.</p>
    </div>
</section>
@endsection
