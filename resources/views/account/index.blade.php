@extends('layouts.app')
@section('title', 'Your appointments — Chair & Blade')
@section('content')
<section class="account-page wrap">
    <div class="account-heading"><div><p class="eyebrow">GOOD TO SEE YOU, {{ strtoupper(auth()->user()->name) }}</p><h1>Your time. <em>Your chair.</em></h1></div><a class="button" href="{{ route('booking.create') }}">Book a visit <span aria-hidden="true">↗</span></a></div>
    @include('auth.feedback')
    @if($nextAppointment)
    <article class="account-card next-appointment"><p class="eyebrow">YOUR NEXT CONFIRMED VISIT</p><h2>{{ $nextAppointment->service->name }}</h2><p>{{ $nextAppointment->starts_at->setTimezone(config('booking.timezone'))->format('l, j F Y') }} · {{ $nextAppointment->starts_at->setTimezone(config('booking.timezone'))->format('H:i') }}–{{ $nextAppointment->ends_at->setTimezone(config('booking.timezone'))->format('H:i') }} (Malta time)</p><a class="text-link" href="{{ route('appointments.show', $nextAppointment) }}">View appointment ↗</a></article>
    @endif
    <div class="account-card"><h2>Your appointments</h2>
        @if($appointments->isEmpty())<p class="empty-state">Your chair is waiting. Book your first appointment to see it here.</p>
        @else
        <div class="table-scroll"><table class="dashboard-table"><thead><tr><th scope="col">Visit</th><th scope="col">Service</th><th scope="col">Booking</th><th scope="col">Payment</th><th scope="col">Details</th></tr></thead><tbody>
        @foreach($appointments as $appointment)
        <tr><td>{{ $appointment->starts_at->setTimezone(config('booking.timezone'))->format('j M Y') }}<small>{{ $appointment->starts_at->setTimezone(config('booking.timezone'))->format('H:i') }} · Malta time</small></td><td>{{ $appointment->service->name }}<small>€{{ number_format($appointment->price_cents / 100, 2) }} · {{ $appointment->duration_minutes }} min</small></td><td><span class="status-badge">{{ ucfirst(str_replace('_', ' ', $appointment->status)) }}</span></td><td>{{ ucfirst(str_replace('_', ' ', $appointment->payment_status ?? 'not_required')) }}</td><td><a class="text-link" href="{{ route('appointments.show', $appointment) }}">View<span class="sr-only"> appointment {{ $appointment->reference }}</span></a></td></tr>
        @endforeach
        </tbody></table></div>{{ $appointments->links() }}
        @endif
    </div>
</section>
@endsection
