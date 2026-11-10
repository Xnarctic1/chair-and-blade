@extends('layouts.app')
@section('title', 'Manage appointments — Chair & Blade')
@section('content')
<section class="account-page wrap">
    <div class="account-heading"><div><p class="eyebrow">THE SHOP DESK</p><h1>Every visit, <em>in view.</em></h1></div><p class="quiet">Appointments, customer details, and payment status.<br>All appointment times are shown in Malta time.</p></div>
    @include('auth.feedback')
    <div class="account-card">
        <form action="{{ route('admin.appointments.index') }}" method="GET" class="admin-filters">
            <div><label for="search">Customer or reference</label><input id="search" name="search" maxlength="100" value="{{ $filters['search'] ?? '' }}" placeholder="Search name, email, or reference"></div>
            <div><label for="status">Booking status</label><select id="status" name="status"><option value="">All bookings</option>@foreach(['confirmed' => 'Confirmed', 'pending_payment' => 'Pending payment', 'cancelled' => 'Cancelled', 'expired' => 'Expired'] as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
            <button class="button" type="submit">Filter</button><a class="text-link" href="{{ route('admin.appointments.index') }}">Clear</a>
        </form>
        <p class="quiet">{{ $appointments->total() }} {{ $appointments->total() === 1 ? 'appointment' : 'appointments' }}</p>
        @if($appointments->isEmpty())<p class="empty-state">No appointments match this view.</p>
        @else<div class="table-scroll"><table class="dashboard-table"><thead><tr><th scope="col">Customer</th><th scope="col">Visit</th><th scope="col">Service</th><th scope="col">Booking</th><th scope="col">Payment</th><th scope="col">Details</th></tr></thead><tbody>
        @foreach($appointments as $appointment)
        <tr><td>{{ $appointment->name }}<small>{{ $appointment->email }}</small></td><td>{{ $appointment->starts_at->setTimezone(config('booking.timezone'))->format('j M Y') }}<small>{{ $appointment->starts_at->setTimezone(config('booking.timezone'))->format('H:i') }}–{{ $appointment->ends_at->setTimezone(config('booking.timezone'))->format('H:i') }}</small></td><td>{{ $appointment->service->name }}<small>€{{ number_format($appointment->price_cents / 100, 2) }}</small></td><td><span class="status-badge">{{ ucfirst(str_replace('_', ' ', $appointment->status)) }}</span></td><td>{{ ucfirst(str_replace('_', ' ', $appointment->payment_status ?? 'not_required')) }}</td><td><a class="text-link" href="{{ route('appointments.show', $appointment) }}">{{ $appointment->reference }}</a></td></tr>
        @endforeach
        </tbody></table></div>{{ $appointments->links() }}@endif
    </div>
</section>
@endsection
