<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\StripeCheckout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function checkout(Request $request, Appointment $appointment, StripeCheckout $stripe): RedirectResponse
    {
        abort_unless($request->user()->id === $appointment->user_id, 403);

        try {
            return redirect()->away($stripe->start($appointment));
        } catch (ValidationException $exception) {
            return to_route('appointments.show', $appointment)->withErrors($exception->errors());
        }
    }

    public function returned(Request $request, Appointment $appointment, StripeCheckout $stripe): RedirectResponse
    {
        abort_unless($request->user()->id === $appointment->user_id, 403);

        try {
            $appointment = $stripe->sync($appointment);
        } catch (ValidationException $exception) {
            return to_route('appointments.show', $appointment)->withErrors($exception->errors());
        }

        $message = match ($appointment->payment_status) {
            'paid' => 'Payment received. Your appointment is confirmed.',
            'expired' => 'The payment window has expired. Please choose a new appointment time.',
            default => 'Payment has not been confirmed. You can return to checkout while your time is held.',
        };

        return to_route('appointments.show', $appointment)->with('status', $message);
    }
}
