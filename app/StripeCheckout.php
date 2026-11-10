<?php

namespace App;

use App\Models\Appointment;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Stripe\ApiRequestor;
use Stripe\Exception\ApiErrorException;
use Stripe\HttpClient\CurlClient;
use Stripe\StripeClient;

class StripeCheckout
{
    public function configured(): bool
    {
        $mode = config('payments.mode');
        $secret = config('payments.secret');
        $webhookSecret = config('payments.webhook_secret');

        return in_array($mode, ['test', 'live'], true)
            && is_string($secret)
            && str_starts_with($secret, $mode === 'live' ? 'sk_live_' : 'sk_test_')
            && is_string($webhookSecret)
            && str_starts_with($webhookSecret, 'whsec_');
    }

    public function start(Appointment $appointment): string
    {
        $this->ensureConfigured();

        try {
            return Cache::lock($this->lockName($appointment), 120)->block(5, function () use ($appointment): string {
                $appointment->refresh();

                if ($appointment->status !== 'pending_payment' || $appointment->payment_status !== 'unpaid') {
                    throw ValidationException::withMessages(['payment' => 'This appointment is not awaiting payment.']);
                }

                if ($appointment->checkout_session_id) {
                    $session = $this->retrieveSession($appointment->checkout_session_id);
                    $this->applySession($session);
                    $appointment->refresh();

                    if (($session['status'] ?? null) === 'open' && $appointment->hold_expires_at?->isFuture()) {
                        return $this->checkoutUrl($session);
                    }

                    throw ValidationException::withMessages(['payment' => 'This checkout is no longer open. Check your appointment status or choose a new time.']);
                }

                if (! $appointment->hold_expires_at?->isFuture()) {
                    $this->expireUnexposedHold($appointment);
                    throw ValidationException::withMessages(['payment' => 'Your reservation has expired. Please choose a new appointment time.']);
                }

                $session = $this->createSession($this->parameters($appointment), 'appointment-checkout-'.$appointment->reference);

                if (! $this->matchesAppointment($appointment, $session) || ! is_string($session['id'] ?? null)) {
                    throw ValidationException::withMessages(['payment' => 'The payment session could not be verified. Please try again shortly.']);
                }

                $appointment->forceFill([
                    'checkout_session_id' => $session['id'],
                    'checkout_url' => ($session['status'] ?? null) === 'open' ? $this->checkoutUrl($session) : null,
                ])->save();
                $this->applySession($session);

                if (($session['status'] ?? null) !== 'open') {
                    throw ValidationException::withMessages(['payment' => 'This checkout has finished. Check your appointment status before trying again.']);
                }

                return $this->checkoutUrl($session);
            });
        } catch (ApiErrorException $exception) {
            Log::warning('Stripe checkout could not be created or retrieved.', ['appointment_id' => $appointment->id, 'type' => $exception::class]);

            throw ValidationException::withMessages(['payment' => 'Stripe is temporarily unavailable. Your time is held while you retry from this page.']);
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages(['payment' => 'Your payment is already being checked. Please try again in a moment.']);
        }
    }

    public function sync(Appointment $appointment): Appointment
    {
        $this->ensureConfigured();

        if ($appointment->checkout_session_id) {
            try {
                $this->applySession($this->retrieveSession($appointment->checkout_session_id));
            } catch (ApiErrorException $exception) {
                Log::warning('Stripe payment status could not be checked.', ['appointment_id' => $appointment->id, 'type' => $exception::class]);
                throw ValidationException::withMessages(['payment' => 'We could not check Stripe yet. Your appointment status will update after payment verification.']);
            }
        }

        return $appointment->refresh();
    }

    /**
     * Apply only signed-webhook data or a session retrieved directly from Stripe.
     *
     * @param  array<string, mixed>  $session
     */
    public function applySession(array $session): bool
    {
        $sessionId = $session['id'] ?? null;

        if (! is_string($sessionId)) {
            return false;
        }

        return DB::transaction(function () use ($session, $sessionId): bool {
            $appointment = Appointment::where('checkout_session_id', $sessionId)->lockForUpdate()->first();

            if (! $appointment || ! $this->matchesAppointment($appointment, $session)) {
                return false;
            }

            if (($session['status'] ?? null) === 'complete' && ($session['payment_status'] ?? null) === 'paid') {
                if ($appointment->payment_status === 'paid') {
                    return true;
                }

                if ($appointment->status !== 'pending_payment' || $appointment->payment_status !== 'unpaid'
                    || ! is_string($session['payment_intent'] ?? null)) {
                    return false;
                }

                $appointment->forceFill([
                    'status' => 'confirmed',
                    'payment_status' => 'paid',
                    'payment_intent_id' => $session['payment_intent'],
                    'paid_at' => now(),
                    'checkout_url' => null,
                ])->save();

                return true;
            }

            if (($session['status'] ?? null) === 'expired' && ($session['payment_status'] ?? null) === 'unpaid'
                && $appointment->status === 'pending_payment' && $appointment->payment_status === 'unpaid') {
                $this->releaseSlots($appointment);

                return true;
            }

            return false;
        }, 5);
    }

    public function reconcile(Appointment $appointment): void
    {
        $this->ensureConfigured();

        Cache::lock($this->lockName($appointment), 120)->block(5, function () use ($appointment): void {
            $appointment->refresh();

            if ($appointment->status !== 'pending_payment' || $appointment->payment_status !== 'unpaid'
                || ! $appointment->hold_expires_at || $appointment->hold_expires_at->isFuture()) {
                return;
            }

            if (! $appointment->checkout_session_id) {
                $this->expireUnexposedHold($appointment);

                return;
            }

            $session = $this->retrieveSession($appointment->checkout_session_id);

            if (($session['status'] ?? null) === 'open') {
                try {
                    $session = $this->expireSession($appointment->checkout_session_id);
                } catch (ApiErrorException) {
                    // Payment may have completed while expiration was requested.
                    $session = $this->retrieveSession($appointment->checkout_session_id);
                }
            }

            $this->applySession($session);
        });
    }

    /** @return array<string, mixed> */
    protected function parameters(Appointment $appointment): array
    {
        $returnUrl = route('payments.return', $appointment);

        return [
            'mode' => 'payment',
            'allowed_payment_method_types' => ['card'],
            'adaptive_pricing' => ['enabled' => false],
            'client_reference_id' => (string) $appointment->id,
            'customer_email' => $appointment->email,
            'metadata' => ['appointment_id' => (string) $appointment->id, 'reference' => $appointment->reference],
            'line_items' => [[
                'price_data' => [
                    'currency' => config('payments.currency'),
                    'unit_amount' => $appointment->price_cents,
                    'product_data' => ['name' => 'Chair & Blade appointment '.$appointment->reference],
                ],
                'quantity' => 1,
            ]],
            'expires_at' => $appointment->hold_expires_at->getTimestamp(),
            'success_url' => $returnUrl.'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $returnUrl.'?cancelled=1',
        ];
    }

    /** @param array<string, mixed> $session */
    protected function matchesAppointment(Appointment $appointment, array $session): bool
    {
        return ($session['mode'] ?? null) === 'payment'
            && ($session['livemode'] ?? null) === (config('payments.mode') === 'live')
            && ($session['currency'] ?? null) === config('payments.currency')
            && ($session['amount_total'] ?? null) === $appointment->price_cents
            && ($session['client_reference_id'] ?? null) === (string) $appointment->id
            && ($session['metadata']['appointment_id'] ?? null) === (string) $appointment->id
            && ($session['metadata']['reference'] ?? null) === $appointment->reference;
    }

    /** @param array<string, mixed> $session */
    protected function checkoutUrl(array $session): string
    {
        $url = $session['url'] ?? null;

        if (! is_string($url) || parse_url($url, PHP_URL_SCHEME) !== 'https' || parse_url($url, PHP_URL_HOST) !== 'checkout.stripe.com') {
            throw ValidationException::withMessages(['payment' => 'Stripe did not return a valid checkout link. Please try again shortly.']);
        }

        return $url;
    }

    protected function ensureConfigured(): void
    {
        if (! $this->configured()) {
            throw ValidationException::withMessages(['payment' => 'Online payment is not configured yet. Please contact the shop or try again later.']);
        }
    }

    protected function lockName(Appointment $appointment): string
    {
        return 'stripe-checkout:'.$appointment->id;
    }

    protected function expireUnexposedHold(Appointment $appointment): void
    {
        // The shared checkout lock ensures a URL cannot be exposed while this hold is released.
        DB::transaction(function () use ($appointment): void {
            $locked = Appointment::whereKey($appointment->id)->lockForUpdate()->firstOrFail();

            if (! $locked->checkout_session_id && $locked->status === 'pending_payment'
                && $locked->payment_status === 'unpaid' && $locked->hold_expires_at?->isPast()) {
                $this->releaseSlots($locked);
            }
        }, 5);
    }

    protected function releaseSlots(Appointment $appointment): void
    {
        DB::table('appointment_slots')->where('appointment_id', $appointment->id)->delete();
        $appointment->forceFill(['status' => 'expired', 'payment_status' => 'expired', 'checkout_url' => null])->save();
    }

    protected function client(): StripeClient
    {
        $http = new CurlClient;
        $http->setTimeout(20);
        $http->setConnectTimeout(5);
        ApiRequestor::setHttpClient($http);

        return new StripeClient(['api_key' => config('payments.secret'), 'max_network_retries' => 0]);
    }

    /** @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    protected function createSession(array $parameters, string $idempotencyKey): array
    {
        return $this->client()->checkout->sessions->create($parameters, ['idempotency_key' => $idempotencyKey])->toArray();
    }

    /** @return array<string, mixed> */
    protected function retrieveSession(string $sessionId): array
    {
        return $this->client()->checkout->sessions->retrieve($sessionId)->toArray();
    }

    /** @return array<string, mixed> */
    protected function expireSession(string $sessionId): array
    {
        return $this->client()->checkout->sessions->expire($sessionId)->toArray();
    }
}
