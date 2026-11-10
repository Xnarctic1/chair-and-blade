<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\User;
use App\StripeCheckout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Stripe\Exception\ApiConnectionException;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['payments.mode' => 'test', 'payments.secret' => 'sk_test_example', 'payments.webhook_secret' => 'whsec_example']);
    }

    private function pending(): Appointment
    {
        return Appointment::factory()->create(['user_id' => User::factory(), 'status' => 'pending_payment', 'payment_status' => 'unpaid', 'hold_expires_at' => now()->addMinutes(35), 'checkout_session_id' => 'cs_test_one', 'starts_at' => now()->addDays(2)]);
    }

    private function stripeSession(Appointment $a, array $overrides = []): array
    {
        return array_replace(['id' => 'cs_test_one', 'mode' => 'payment', 'livemode' => false, 'status' => 'complete', 'payment_status' => 'paid', 'payment_intent' => 'pi_test_one', 'currency' => 'eur', 'amount_total' => $a->price_cents, 'client_reference_id' => (string) $a->id, 'metadata' => ['appointment_id' => (string) $a->id, 'reference' => $a->reference], 'url' => 'https://checkout.stripe.com/c/pay/cs_test_one'], $overrides);
    }

    private function event(array $session, string $type = 'checkout.session.completed', bool $valid = true): TestResponse
    {
        $json = json_encode(['id' => 'evt_test', 'object' => 'event', 'type' => $type, 'data' => ['object' => $session]]);
        $time = time();
        $signature = hash_hmac('sha256', $time.'.'.$json, $valid ? 'whsec_example' : 'wrong');

        return $this->call('POST', '/stripe/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => "t={$time},v1={$signature}"], $json);
    }

    public function test_signed_paid_webhook_confirms_once_and_later_expiry_cannot_release_it(): void
    {
        $a = $this->pending();
        $session = $this->stripeSession($a);
        $this->event($session)->assertOk();
        $paidAt = $a->fresh()->paid_at;
        $this->event($session)->assertOk();
        $this->event($this->stripeSession($a, ['status' => 'expired', 'payment_status' => 'unpaid']), 'checkout.session.expired')->assertOk();
        $this->assertSame('paid', $a->fresh()->payment_status);
        $this->assertSame('confirmed', $a->fresh()->status);
        $this->assertTrue($a->fresh()->paid_at->equalTo($paidAt));
        $this->assertDatabaseCount('appointment_slots', 3);
    }

    public function test_unsigned_or_forged_event_cannot_confirm(): void
    {
        $a = $this->pending();
        $this->event($this->stripeSession($a), valid: false)->assertStatus(400);
        $this->assertSame('unpaid', $a->fresh()->payment_status);
    }

    public function test_wrong_amount_currency_identity_mode_and_unpaid_completion_are_rejected(): void
    {
        $a = $this->pending();
        foreach ([['amount_total' => 1], ['currency' => 'usd'], ['client_reference_id' => '999'], ['metadata' => ['appointment_id' => '999', 'reference' => $a->reference]], ['livemode' => true], ['payment_status' => 'unpaid']] as $bad) {
            $this->event($this->stripeSession($a, $bad))->assertOk();
            $this->assertSame('unpaid', $a->fresh()->payment_status);
        }
    }

    public function test_verified_expired_session_releases_occupied_slots(): void
    {
        $a = $this->pending();
        $this->event($this->stripeSession($a, ['status' => 'expired', 'payment_status' => 'unpaid']), 'checkout.session.expired')->assertOk();
        $this->assertSame('expired', $a->fresh()->status);
        $this->assertDatabaseCount('appointment_slots', 0);
    }

    public function test_checkout_cannot_be_accessed_by_another_customer(): void
    {
        $a = $this->pending();
        $this->actingAs(User::factory()->create())->post(route('payments.checkout', $a))->assertForbidden();
        $this->get(route('payments.return', $a))->assertForbidden();
    }

    public function test_success_url_alone_does_not_mark_payment_paid(): void
    {
        $a = $this->pending();
        $session = $this->stripeSession($a, ['status' => 'open', 'payment_status' => 'unpaid']);
        $stripe = \Mockery::mock(StripeCheckout::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $stripe->shouldReceive('retrieveSession')->with('cs_test_one')->once()->andReturn($session);
        $this->app->instance(StripeCheckout::class, $stripe);
        $this->actingAs($a->user)->get(route('payments.return', $a).'?session_id=cs_fake&paid=true')->assertRedirect(route('appointments.show', $a));
        $this->assertSame('unpaid', $a->fresh()->payment_status);
    }

    public function test_checkout_uses_snapshot_price_and_reuses_one_session(): void
    {
        $a = $this->pending();
        $a->update(['checkout_session_id' => null]);
        $session = $this->stripeSession($a, ['status' => 'open', 'payment_status' => 'unpaid']);
        $stripe = \Mockery::mock(StripeCheckout::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $stripe->shouldReceive('createSession')->once()->withArgs(fn ($p, $key) => $p['line_items'][0]['price_data']['unit_amount'] === $a->price_cents && $p['allowed_payment_method_types'] === ['card'] && $p['line_items'][0]['price_data']['currency'] === 'eur' && $key === 'appointment-checkout-'.$a->reference)->andReturn($session);
        $stripe->shouldReceive('retrieveSession')->once()->with('cs_test_one')->andReturn($session);
        $this->assertSame($session['url'], $stripe->start($a));
        $this->assertSame($session['url'], $stripe->start($a));
        $this->assertSame('cs_test_one', $a->fresh()->checkout_session_id);
    }

    public function test_checkout_returns_to_the_current_browser_address_when_app_url_is_stale(): void
    {
        config(['app.url' => 'http://localhost:8000']);
        $appointment = $this->pending();
        $appointment->update(['checkout_session_id' => null]);
        $session = $this->stripeSession($appointment, ['status' => 'open', 'payment_status' => 'unpaid']);
        $returnUrl = 'http://127.0.0.1:8001/account/appointments/'.$appointment->id.'/payment';

        $stripe = \Mockery::mock(StripeCheckout::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $stripe->shouldReceive('createSession')->once()->withArgs(function (array $parameters, string $key) use ($appointment, $returnUrl): bool {
            $this->assertSame($returnUrl.'?session_id={CHECKOUT_SESSION_ID}', $parameters['success_url']);
            $this->assertSame($returnUrl.'?cancelled=1', $parameters['cancel_url']);

            return $key === 'appointment-checkout-'.$appointment->reference;
        })->andReturn($session);
        $this->app->instance(StripeCheckout::class, $stripe);

        $this->actingAs($appointment->user)
            ->post('http://127.0.0.1:8001/account/appointments/'.$appointment->id.'/checkout')
            ->assertRedirect($session['url']);
    }

    public function test_reconciliation_does_not_free_a_checkout_which_completed_during_expiry(): void
    {
        $a = $this->pending();
        $a->update(['hold_expires_at' => now()->subMinute()]);
        $stripe = \Mockery::mock(StripeCheckout::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $stripe->shouldReceive('retrieveSession')->once()->andReturn($this->stripeSession($a, ['status' => 'open', 'payment_status' => 'unpaid']));
        $stripe->shouldReceive('expireSession')->once()->andReturn($this->stripeSession($a));
        $stripe->reconcile($a);
        $this->assertSame('paid', $a->fresh()->payment_status);
        $this->assertDatabaseCount('appointment_slots', 3);
    }

    public function test_api_failure_keeps_existing_hold_for_retry(): void
    {
        $a = $this->pending();
        $a->update(['hold_expires_at' => now()->subMinute()]);
        $stripe = \Mockery::mock(StripeCheckout::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $stripe->shouldReceive('retrieveSession')->andThrow(new ApiConnectionException('Offline'));
        $this->app->instance(StripeCheckout::class, $stripe);
        $this->artisan('payments:reconcile')->assertFailed();
        $this->assertSame('pending_payment', $a->fresh()->status);
        $this->assertDatabaseCount('appointment_slots', 3);
    }

    public function test_missing_keys_and_live_key_in_test_mode_are_not_configured(): void
    {
        config(['payments.secret' => null]);
        $this->assertFalse(app(StripeCheckout::class)->configured());
        config(['payments.secret' => 'sk_live_example']);
        $this->assertFalse(app(StripeCheckout::class)->configured());
    }
}
