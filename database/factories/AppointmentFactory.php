<?php

namespace Database\Factories;

use App\AppointmentScheduler;
use App\Models\Appointment;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'CB-'.Str::upper(Str::random(8)),
            'service_id' => Service::factory(),
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'starts_at' => CarbonImmutable::now(config('booking.timezone'))->next(CarbonImmutable::TUESDAY)->setTime(9, 0)->utc(),
            'duration_minutes' => fn (array $attributes): int => Service::findOrFail($attributes['service_id'])->duration_minutes,
            'ends_at' => fn (array $attributes): CarbonImmutable => CarbonImmutable::parse($attributes['starts_at'])->addMinutes($attributes['duration_minutes']),
            'price_cents' => fn (array $attributes): int => Service::findOrFail($attributes['service_id'])->price_cents,
            'status' => 'confirmed',
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Appointment $appointment): void {
            DB::table('appointment_slots')->insert(array_map(
                fn (string $time): array => ['appointment_id' => $appointment->id, 'starts_at' => $time],
                app(AppointmentScheduler::class)->intervals($appointment->starts_at, $appointment->duration_minutes),
            ));
        });
    }
}
