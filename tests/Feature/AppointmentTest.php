<?php

namespace Tests\Feature;

use App\AppointmentScheduler;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use App\StripeCheckout;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AppointmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->actingAs(User::factory()->create(['name' => 'Alex Smith', 'email' => 'alex@example.com']));
        $this->mock(StripeCheckout::class, function ($mock): void {
            $mock->shouldReceive('configured')->andReturn(true);
            $mock->shouldReceive('start')->andReturn('https://checkout.stripe.com/c/pay/cs_test_mock');
        });
        $this->travelTo(CarbonImmutable::parse('2026-11-10 08:00', 'Europe/Malta'));
    }

    public function test_public_pages_show_database_services(): void
    {
        $service = Service::factory()->create();
        $this->get('/')->assertOk()->assertSee($service->name);
        $this->get('/book')->assertOk()->assertSee($service->name);
    }

    public function test_new_appointments_preserve_existing_pending_booking_requests(): void
    {
        $service = Service::factory()->create();
        $legacyRequestId = DB::table('booking_requests')->insertGetId([
            'service_id' => $service->id,
            'name' => 'Alex Smith',
            'email' => 'alex@example.com',
            'preferred_date' => '2026-11-11',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->post('/book', $this->details($service))
            ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_mock');

        $this->assertDatabaseHas('booking_requests', ['id' => $legacyRequestId, 'status' => 'pending']);
        $this->assertDatabaseCount('booking_requests', 1);
        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_invalid_service_date_and_time_are_rejected(): void
    {
        $this->post('/book', ['service_id' => 999, 'date' => '2026-11-09', 'time' => '25:00'])
            ->assertSessionHasErrors(['service_id', 'date', 'time']);
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_dates_outside_booking_window_are_rejected(): void
    {
        $service = Service::factory()->create();
        foreach (['2026-11-09', '2027-01-10'] as $date) {
            $this->post('/book', $this->details($service, ['date' => $date]))
                ->assertSessionHasErrors('date');
        }
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_appointment_awaits_payment_with_server_owned_duration_price_and_status(): void
    {
        $service = Service::factory()->create(['duration_minutes' => 45, 'price_cents' => 2800]);

        $this->post('/book', $this->details($service) + ['status' => 'pending_payment',
            'payment_status' => 'unpaid', 'price_cents' => 1, 'duration_minutes' => 15])
            ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_mock');

        $this->assertDatabaseHas('appointments', [
            'service_id' => $service->id,
            'name' => 'Alex Smith',
            'email' => 'alex@example.com',
            'starts_at' => '2026-11-11 08:00:00',
            'ends_at' => '2026-11-11 08:45:00',
            'duration_minutes' => 45,
            'price_cents' => 2800,
            'status' => 'pending_payment',
            'payment_status' => 'unpaid',
        ]);
        $this->assertDatabaseCount('appointment_slots', 3);
        $this->assertDatabaseCount('booking_requests', 0);
    }

    public function test_calendar_covers_whole_weeks_and_defaults_to_the_first_available_date(): void
    {
        $service = Service::factory()->create(['duration_minutes' => 45]);

        $this->get('/book')->assertOk()
            ->assertViewHas('selectedService', fn (Service $selected): bool => $selected->is($service))
            ->assertViewHas('selectedDate', '2026-11-10')
            ->assertViewHas('timezone', 'Europe/Malta')
            ->assertViewHas('minDate', '2026-11-10')
            ->assertViewHas('maxDate', '2027-01-09')
            ->assertViewHas('previousMonth', null)
            ->assertViewHas('nextMonth', '2026-12')
            ->assertViewHas('calendarDays', function (array $days): bool {
                $byDate = array_column($days, null, 'date');

                return count($days) === 42
                    && $days[0]['date'] === '2026-10-26'
                    && $days[41]['date'] === '2026-12-06'
                    && ! $byDate['2026-11-09']['available']
                    && ! $byDate['2026-11-15']['available']
                    && ! $byDate['2026-11-16']['available']
                    && ! $byDate['2026-12-01']['inMonth']
                    && ! $byDate['2026-12-01']['available']
                    && $byDate['2026-11-10']['slotCount'] === 28;
            });
    }

    public function test_service_duration_changes_slots_and_disallows_lunch_and_closing_overruns(): void
    {
        $short = Service::factory()->create(['duration_minutes' => 30]);
        $long = Service::factory()->create(['duration_minutes' => 75]);

        $this->get(route('booking.create', ['service' => $short->id, 'date' => '2026-11-11']))
            ->assertViewHas('slots', fn (array $slots): bool => in_array('12:30', $slots, true) && in_array('17:30', $slots, true));

        $this->get(route('booking.create', ['service' => $long->id, 'date' => '2026-11-11']))
            ->assertViewHas('selectedService', fn (Service $selected): bool => $selected->is($long))
            ->assertViewHas('slots', fn (array $slots): bool => in_array('11:45', $slots, true)
                && ! in_array('12:00', $slots, true)
                && ! in_array('13:00', $slots, true)
                && in_array('16:45', $slots, true)
                && ! in_array('17:00', $slots, true));
    }

    public function test_today_shows_only_future_times_and_allows_booking_one(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-10 10:07', 'Europe/Malta'));
        $service = Service::factory()->create();

        $this->get('/book')->assertViewHas('selectedDate', '2026-11-10')
            ->assertViewHas('slots', fn (array $slots): bool => $slots[0] === '11:00');

        $this->post('/book', $this->details($service, ['date' => '2026-11-10', 'time' => '11:00']))
            ->assertSessionHasNoErrors()->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_mock');
    }

    #[DataProvider('unavailableTimes')]
    public function test_unavailable_times_are_rejected(string $date, string $time): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-10 10:07', 'Europe/Malta'));
        $service = Service::factory()->create(['duration_minutes' => 45]);

        $this->post('/book', $this->details($service, ['date' => $date, 'time' => $time]))
            ->assertSessionHasErrors('time');

        $this->assertDatabaseCount('appointments', 0);
        $this->assertDatabaseCount('appointment_slots', 0);
    }

    public static function unavailableTimes(): array
    {
        return [
            'past today' => ['2026-11-10', '10:00'],
            'Sunday' => ['2026-11-15', '09:00'],
            'Monday' => ['2026-11-16', '09:00'],
            'before opening' => ['2026-11-11', '08:45'],
            'crossing lunch' => ['2026-11-11', '12:30'],
            'during lunch' => ['2026-11-11', '13:30'],
            'crossing closing' => ['2026-11-11', '17:30'],
            'after closing' => ['2026-11-11', '18:00'],
            'not on cadence' => ['2026-11-11', '09:10'],
        ];
    }

    public function test_exact_current_time_is_not_bookable(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-10 10:15', 'Europe/Malta'));
        $service = Service::factory()->create();

        $this->post('/book', $this->details($service, ['date' => '2026-11-10', 'time' => '10:15']))
            ->assertSessionHasErrors('time');
    }

    public function test_booking_window_includes_day_sixty_but_disables_later_dates(): void
    {
        $service = Service::factory()->create();

        $this->get('/book?month=2027-01')->assertViewHas('nextMonth', null)
            ->assertViewHas('previousMonth', '2026-12')
            ->assertViewHas('calendarDays', function (array $days): bool {
                $byDate = array_column($days, null, 'date');

                return $byDate['2027-01-09']['available'] && ! $byDate['2027-01-12']['available'];
            });

        $this->post('/book', $this->details($service, ['date' => '2027-01-09']))->assertSessionHasNoErrors();
        $this->post('/book', $this->details($service, ['date' => '2027-01-12']))->assertSessionHasErrors('date');
        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_overlaps_are_blocked_across_services_but_back_to_back_appointments_are_allowed(): void
    {
        $long = Service::factory()->create(['duration_minutes' => 75]);
        $short = Service::factory()->create(['duration_minutes' => 30]);

        $this->post('/book', $this->details($long, ['time' => '10:00']))->assertSessionHasNoErrors();
        $this->post('/book', $this->details($short, ['time' => '10:00']))->assertSessionHasErrors('time');
        $this->post('/book', $this->details($short, ['time' => '09:45']))->assertSessionHasErrors('time');
        $this->post('/book', $this->details($short, ['time' => '11:00']))->assertSessionHasErrors('time');
        $this->post('/book', $this->details($short, ['time' => '11:15']))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('appointments', 2);
        $this->assertDatabaseCount('appointment_slots', 7);

        $this->get(route('booking.create', ['service' => $short->id, 'date' => '2026-11-11']))
            ->assertViewHas('slots', fn (array $slots): bool => in_array('09:30', $slots, true)
                && ! in_array('09:45', $slots, true)
                && ! in_array('10:30', $slots, true)
                && in_array('11:45', $slots, true));
    }

    public function test_stale_availability_is_still_rejected_by_database_and_rolls_back_the_appointment(): void
    {
        $service = Service::factory()->create();
        Appointment::factory()->create(['service_id' => $service->id, 'starts_at' => CarbonImmutable::parse('2026-11-11 09:00', 'Europe/Malta')->utc()]);
        $this->app->bind(AppointmentScheduler::class, fn (): AppointmentScheduler => new class extends AppointmentScheduler
        {
            public function occupiedBetween(CarbonImmutable $start, CarbonImmutable $end): array
            {
                return [];
            }
        });

        $this->post('/book', $this->details($service, ['time' => '09:15']))
            ->assertSessionHasErrors('time');

        $this->assertDatabaseCount('appointments', 1);
        $this->assertDatabaseCount('appointment_slots', 3);
    }

    #[DataProvider('malformedQueries')]
    public function test_malformed_calendar_queries_fall_back_safely(array $query): void
    {
        Service::factory()->create();

        $this->get(route('booking.create', $query))->assertOk()
            ->assertViewHas('selectedDate', '2026-11-10');
    }

    public static function malformedQueries(): array
    {
        return [
            'array inputs' => [['service' => ['1'], 'month' => ['2026-11'], 'date' => ['2026-11-11']]],
            'invalid dates' => [['service' => 'unknown', 'month' => '2026-99', 'date' => '2026-02-31']],
            'past month' => [['month' => '2020-01']],
        ];
    }

    public function test_validation_preserves_selected_service_date_and_time(): void
    {
        Service::factory()->create();
        $service = Service::factory()->create(['duration_minutes' => 30]);

        $this->from('/book')->post('/book', $this->details($service, ['date' => '2026-12-09', 'time' => 'invalid']))
            ->assertRedirect('/book')->assertSessionHasErrors('time')->assertSessionHasInput('time', 'invalid');

        $this->get('/book')
            ->assertViewHas('selectedService', fn (Service $selected): bool => $selected->is($service))
            ->assertViewHas('selectedDate', '2026-12-09')
            ->assertViewHas('month', fn (CarbonImmutable $month): bool => $month->format('Y-m') === '2026-12')
            ->assertSee('Pick your time');
    }

    public function test_no_services_returns_an_empty_calendar_without_errors(): void
    {
        $this->get('/book')->assertOk()->assertViewHas('selectedService', null)
            ->assertViewHas('selectedDate', null)->assertViewHas('slots', []);
    }

    public function test_booking_dates_follow_shop_timezone_at_utc_midnight(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-10 23:30', 'UTC'));
        Service::factory()->create();

        $this->get('/book')->assertViewHas('minDate', '2026-11-11')->assertViewHas('selectedDate', '2026-11-11');
    }

    public function test_array_post_values_return_validation_feedback_without_breaking_the_booking_page(): void
    {
        $service = Service::factory()->create();

        $this->followingRedirects()->from('/book')->post('/book', $this->details($service, [
            'service_id' => [$service->id],
            'date' => ['2026-11-11'],
            'time' => ['09:00'],
            'name' => ['Alex'],
            'email' => ['alex@example.com'],
        ]))->assertOk()->assertSee('Let’s check a few details.');
        $this->assertDatabaseCount('appointments', 0);
        $this->assertDatabaseCount('appointment_slots', 0);
    }

    #[DataProvider('localBookingDates')]
    public function test_persisted_utc_appointments_display_the_original_local_time_in_confirmation(
        string $today,
        string $date,
        string $expectedUtcStart,
        string $expectedUtcEnd,
    ): void {
        $this->travelTo(CarbonImmutable::parse($today.' 08:00', 'Europe/Malta'));
        $service = Service::factory()->create(['duration_minutes' => 45]);

        $this->post('/book', $this->details($service, ['date' => $date, 'time' => '10:15']))
            ->assertSessionHasNoErrors()->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_mock');

        $appointment = Appointment::firstOrFail();
        $this->assertSame($expectedUtcStart, $appointment->starts_at->toDateTimeString());
        $this->assertSame($expectedUtcEnd, $appointment->ends_at->toDateTimeString());
        $this->assertSame('10:15', $appointment->starts_at->setTimezone('Europe/Malta')->format('H:i'));
        $this->assertSame('11:00', $appointment->ends_at->setTimezone('Europe/Malta')->format('H:i'));

        $this->get(route('booking.create', ['service' => $service->id, 'date' => $date]))
            ->assertOk()
            ->assertViewHas('slots', fn (array $slots): bool => ! in_array('10:15', $slots, true));
    }

    public static function localBookingDates(): array
    {
        return [
            'winter CET' => ['2026-11-10', '2026-11-11', '2026-11-11 09:15:00', '2026-11-11 10:00:00'],
            'summer CEST' => ['2027-07-06', '2027-07-07', '2027-07-07 08:15:00', '2027-07-07 09:00:00'],
        ];
    }

    /** @return array{service_id: int, date: string, time: string, name: string, email: string} */
    private function details(Service $service, array $overrides = []): array
    {
        return array_replace([
            'service_id' => $service->id,
            'date' => '2026-11-11',
            'time' => '09:00',
            'name' => 'Alex Smith',
            'email' => 'alex@example.com',
        ], $overrides);

    }
}
