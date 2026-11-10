<?php

namespace Tests\Feature;

use App\Mail\AppointmentConfirmation;
use App\Mail\AppointmentReminder;
use App\Models\Appointment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AppointmentEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['booking.emails_enabled' => true]);
        Mail::fake();
    }

    public function test_confirmation_and_next_day_reminder_are_sent_once(): void
    {
        $appointment = Appointment::factory()->create(['user_id' => User::factory(), 'payment_status' => 'paid', 'paid_at' => now(), 'starts_at' => now()->addDays(2)]);
        $this->artisan('appointments:send-emails')->assertSuccessful();
        Mail::assertSent(AppointmentConfirmation::class, 1);
        Mail::assertNotSent(AppointmentReminder::class);
        $this->artisan('appointments:send-emails')->assertSuccessful();
        Mail::assertSent(AppointmentConfirmation::class, 1);
        $this->travel(25)->hours();
        $this->artisan('appointments:send-emails')->assertSuccessful();
        $this->artisan('appointments:send-emails')->assertSuccessful();
        Mail::assertSent(AppointmentReminder::class, fn ($mail) => $mail->hasTo($appointment->email));
        Mail::assertSent(AppointmentReminder::class, 1);
        $this->assertNotNull($appointment->fresh()->reminder_sent_at);
    }

    public function test_unpaid_expired_and_past_appointments_never_receive_reminders(): void
    {
        Appointment::factory()->create(['status' => 'pending_payment', 'payment_status' => 'unpaid', 'starts_at' => now()->addHours(5)]);
        Appointment::factory()->create(['status' => 'expired', 'payment_status' => 'expired', 'starts_at' => now()->addHours(7)]);
        Appointment::factory()->create(['payment_status' => 'paid', 'starts_at' => now()->subDays(1)]);
        $this->artisan('appointments:send-emails')->assertSuccessful();
        Mail::assertNothingSent();
    }

    public function test_short_notice_booking_gets_one_confirmation_instead_of_two_identical_emails(): void
    {
        $appointment = Appointment::factory()->create(['payment_status' => 'paid', 'starts_at' => now()->addHours(3)]);
        $this->artisan('appointments:send-emails')->assertSuccessful();
        $this->artisan('appointments:send-emails')->assertSuccessful();
        Mail::assertSent(AppointmentConfirmation::class, 1);
        Mail::assertNotSent(AppointmentReminder::class);
        $this->assertNotNull($appointment->fresh()->reminder_sent_at);
    }

    public function test_delivery_failure_leaves_email_eligible_for_retry(): void
    {
        $appointment = Appointment::factory()->create(['payment_status' => 'paid', 'starts_at' => now()->addDays(2)]);
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('Test transport failure'));
        $this->artisan('appointments:send-emails')->assertFailed();
        $this->assertNull($appointment->fresh()->confirmation_sent_at);
    }

    public function test_email_delivery_can_be_disabled(): void
    {
        config(['booking.emails_enabled' => false]);
        Appointment::factory()->create(['payment_status' => 'paid', 'starts_at' => now()->addDays(2)]);
        $this->artisan('appointments:send-emails')->assertSuccessful();
        Mail::assertNothingSent();
    }

    public function test_mail_template_shows_local_time_and_account_link(): void
    {
        $appointment = Appointment::factory()->create(['starts_at' => CarbonImmutable::parse('2026-11-11 08:00', 'UTC')]);
        $html = (new AppointmentReminder($appointment))->render();
        $this->assertStringContainsString('09:00', $html);
        $this->assertStringContainsString(route('appointments.show', $appointment), $html);
    }
}
