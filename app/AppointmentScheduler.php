<?php

namespace App;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConcurrencyErrorDetector;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AppointmentScheduler
{
    public function timezone(): string
    {
        return config('booking.timezone');
    }

    public function minDate(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone())->startOfDay();
    }

    public function maxDate(): CarbonImmutable
    {
        return $this->minDate()->addDays(config('booking.days_ahead'));
    }

    public function parseDate(mixed $date): ?CarbonImmutable
    {
        if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        try {
            $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $date, $this->timezone());

            return $parsed && $parsed->toDateString() === $date ? $parsed : null;
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    /** @return array<string, true> */
    public function occupiedBetween(CarbonImmutable $start, CarbonImmutable $end): array
    {
        return DB::table('appointment_slots')
            ->where('starts_at', '>=', $start->utc()->toDateTimeString())
            ->where('starts_at', '<', $end->utc()->toDateTimeString())
            ->pluck('starts_at')
            ->mapWithKeys(fn (string $time): array => [$time => true])
            ->all();
    }

    /**
     * @param  array<string, true>|null  $occupied
     * @return list<string>
     */
    public function slotsForDate(Service $service, CarbonImmutable $date, ?array $occupied = null): array
    {
        $interval = config('booking.slot_minutes');

        if ($date->startOfDay()->lt($this->minDate()) || $date->startOfDay()->gt($this->maxDate())
            || $service->duration_minutes <= 0 || $service->duration_minutes % $interval !== 0) {
            return [];
        }

        $occupied ??= $this->occupiedBetween($date->startOfDay(), $date->addDay()->startOfDay());
        $now = CarbonImmutable::now($this->timezone());
        $slots = [];

        foreach (config('booking.opening_hours')[$date->isoWeekday()] ?? [] as [$opens, $closes]) {
            $closing = $date->setTimeFromTimeString($closes);

            for ($start = $date->setTimeFromTimeString($opens); $start->addMinutes($service->duration_minutes)->lte($closing); $start = $start->addMinutes($interval)) {
                if ($start->lt($now->addMinutes(config('booking.minimum_notice_minutes')))) {
                    continue;
                }

                $free = true;

                foreach ($this->intervals($start, $service->duration_minutes) as $time) {
                    if (isset($occupied[$time])) {
                        $free = false;
                        break;
                    }
                }

                if ($free) {
                    $slots[] = $start->format('H:i');
                }
            }
        }

        return $slots;
    }

    /** @return list<string> */
    public function intervals(CarbonImmutable $start, int $durationMinutes): array
    {
        $intervals = [];

        for ($offset = 0; $offset < $durationMinutes; $offset += config('booking.slot_minutes')) {
            $intervals[] = $start->addMinutes($offset)->utc()->toDateTimeString();
        }

        return $intervals;
    }

    /** @param array{service_id: int|string, date: string, time: string, name: string, email: string} $details */
    public function book(array $details, ?User $user = null, bool $requiresPayment = false): Appointment
    {
        try {
            return DB::transaction(function () use ($details, $user, $requiresPayment): Appointment {
                $service = Service::findOrFail($details['service_id']);
                $date = $this->parseDate($details['date']);

                if (! $date || ! in_array($details['time'], $this->slotsForDate($service, $date), true)) {
                    throw ValidationException::withMessages(['time' => 'That appointment time is no longer available. Please choose another time.']);
                }

                $start = $date->setTimeFromTimeString($details['time']);
                $appointment = Appointment::create([
                    'reference' => 'CB-'.Str::upper(Str::random(8)),
                    'service_id' => $service->id,
                    'user_id' => $user?->id,
                    'name' => $user?->name ?? $details['name'],
                    'email' => $user?->email ?? $details['email'],
                    'starts_at' => $start->utc(),
                    'ends_at' => $start->addMinutes($service->duration_minutes)->utc(),
                    'duration_minutes' => $service->duration_minutes,
                    'price_cents' => $service->price_cents,
                    'status' => $requiresPayment ? 'pending_payment' : 'confirmed',
                    'payment_status' => $requiresPayment ? 'unpaid' : 'not_required',
                    'hold_expires_at' => $requiresPayment ? CarbonImmutable::now('UTC')->addMinutes(config('booking.hold_minutes')) : null,
                ]);

                DB::table('appointment_slots')->insert(array_map(
                    fn (string $time): array => ['appointment_id' => $appointment->id, 'starts_at' => $time],
                    $this->intervals($start, $service->duration_minutes),
                ));

                return $appointment->setRelation('service', $service);
            }, 5);
        } catch (QueryException $exception) {
            if (($exception instanceof UniqueConstraintViolationException && str_contains($exception->getSql(), 'appointment_slots'))
                || (new ConcurrencyErrorDetector)->causedByConcurrencyError($exception)) {
                throw ValidationException::withMessages(['time' => 'Someone has just booked that time. Please choose another available appointment.']);
            }

            throw $exception;
        }
    }
}
