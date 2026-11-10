<?php

namespace Tests\Feature;

use App\AppointmentScheduler;
use App\Models\Service;
use App\Models\User;
use App\StripeCheckout;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutBookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-11-10 08:00', 'Europe/Malta'));
    }

    public function test_booking_identity_comes_from_account_not_posted_fields(): void
    {
        $user = User::factory()->create();
        $service = Service::factory()->create();
        $this->mock(StripeCheckout::class, function ($mock) {
            $mock->shouldReceive('configured')->andReturn(true);
            $mock->shouldReceive('start')->once()->andReturn('https://checkout.stripe.com/c/pay/cs_test_mock');
        });
        $this->actingAs($user)->post('/book', ['service_id' => $service->id, 'date' => '2026-11-11', 'time' => '10:00', 'name' => 'Someone else', 'email' => 'someone@example.com', 'user_id' => 999, 'status' => 'confirmed'])->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_mock');
        $this->assertDatabaseHas('appointments', ['user_id' => $user->id, 'email' => $user->email, 'name' => $user->name, 'status' => 'pending_payment', 'payment_status' => 'unpaid']);
    }

    public function test_unconfigured_payments_do_not_reserve_a_time(): void
    {
        config(['payments.secret' => null]);
        $service = Service::factory()->create();
        $this->actingAs(User::factory()->create())->post('/book', ['service_id' => $service->id, 'date' => '2026-11-11', 'time' => '10:00'])->assertSessionHasErrors('payment');
        $this->assertDatabaseCount('appointments', 0);
        $this->assertDatabaseCount('appointment_slots', 0);
    }

    public function test_time_less_than_45_minutes_away_cannot_be_reserved(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-10 10:07', 'Europe/Malta'));
        $service = Service::factory()->create();
        $slots = app(AppointmentScheduler::class)->slotsForDate($service, CarbonImmutable::parse('2026-11-10', 'Europe/Malta'));
        $this->assertNotContains('10:45', $slots);
        $this->assertContains('11:00', $slots);
    }
}
