<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Chair & Blade appointment</title></head>
<body style="margin:0;padding:32px 16px;background:#f4f1e9;color:#252921;font-family:Arial,sans-serif;line-height:1.7">
<table role="presentation" style="max-width:560px;width:100%;margin:auto;background:#faf9f3;border:1px solid #d7d5cb;border-collapse:collapse"><tr><td style="padding:32px">
<p style="font-size:12px;letter-spacing:3px;color:#ac4a2d">CHAIR & BLADE</p>
<h1 style="font-family:Georgia,serif;font-weight:400;font-size:32px">{{ $isReminder ? 'Your chair is waiting.' : 'You’re booked in.' }}</h1>
<p>Hello {{ $appointment->name }},</p>
<p>{{ $isReminder ? 'A quick reminder about your upcoming appointment.' : 'Your payment is confirmed and your appointment is reserved.' }}</p>
<p><strong>{{ $appointment->service->name }}</strong><br>
{{ $appointment->starts_at->setTimezone(config('booking.timezone'))->format('l, j F Y') }}<br>
{{ $appointment->starts_at->setTimezone(config('booking.timezone'))->format('H:i') }}–{{ $appointment->ends_at->setTimezone(config('booking.timezone'))->format('H:i') }} · Malta time<br>
{{ $appointment->duration_minutes }} minutes · €{{ number_format($appointment->price_cents / 100, 2) }} paid</p>
<p>Booking reference: <strong>{{ $appointment->reference }}</strong></p>
<p><a href="{{ route('appointments.show', $appointment) }}" style="display:inline-block;background:#ac4a2d;color:#fff;padding:12px 20px;text-decoration:none">View your appointment</a></p>
<p style="font-size:12px;color:#64665b">Chair & Blade is a portfolio demo. This booking does not reserve an appointment at a real shop.</p>
</td></tr></table>
</body></html>
