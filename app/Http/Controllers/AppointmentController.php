<?php

namespace App\Http\Controllers;

use App\AppointmentScheduler;
use App\Http\Requests\StoreAppointmentRequest;
use App\Models\Service;
use App\StripeCheckout;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AppointmentController extends Controller
{
    public function create(Request $request, AppointmentScheduler $scheduler): View
    {
        $services = Service::orderBy('id')->get();
        $serviceId = $request->old('service_id', $request->query('service'));
        $selectedService = (is_string($serviceId) || is_int($serviceId)) ? $services->firstWhere('id', $serviceId) : null;
        $selectedService ??= $services->first();
        $requestedDate = $scheduler->parseDate($request->old('date', $request->query('date')));
        $requestedMonth = $request->query('month');
        $month = is_string($requestedMonth) ? $scheduler->parseDate($requestedMonth.'-01') : null;

        if ($request->hasSession() && $request->session()->hasOldInput('date') && $requestedDate) {
            $month = $requestedDate->startOfMonth();
        }

        $month ??= ($requestedDate ?? $scheduler->minDate())->startOfMonth();
        $month = $month->max($scheduler->minDate()->startOfMonth())->min($scheduler->maxDate()->startOfMonth());
        $firstDay = $month->startOfWeek(CarbonImmutable::MONDAY);
        $lastDay = $month->endOfMonth()->endOfWeek(CarbonImmutable::SUNDAY)->startOfDay();
        $occupied = $scheduler->occupiedBetween($firstDay, $lastDay->addDay());
        $calendarDays = [];
        $availableDates = [];

        for ($date = $firstDay; $date->lte($lastDay); $date = $date->addDay()) {
            $inMonth = $date->month === $month->month;
            $dateSlots = $inMonth && $selectedService ? $scheduler->slotsForDate($selectedService, $date, $occupied) : [];
            $dateString = $date->toDateString();
            $calendarDays[] = ['date' => $dateString, 'day' => $date->day, 'inMonth' => $inMonth, 'available' => count($dateSlots) > 0, 'slotCount' => count($dateSlots)];

            if ($dateSlots !== []) {
                $availableDates[$dateString] = $dateSlots;
            }
        }

        $selectedDate = $requestedDate && $requestedDate->isSameMonth($month)
            && $requestedDate->betweenIncluded($scheduler->minDate(), $scheduler->maxDate())
            ? $requestedDate->toDateString()
            : array_key_first($availableDates);

        if (! $request->user()) {
            $request->session()->put('url.intended', route('booking.create', ['service' => $selectedService?->id, 'date' => $selectedDate]));
        }

        return view('booking', [
            'paymentsReady' => app(StripeCheckout::class)->configured(),
            'services' => $services,
            'selectedService' => $selectedService,
            'month' => $month,
            'selectedDate' => $selectedDate,
            'calendarDays' => $calendarDays,
            'slots' => $availableDates[$selectedDate] ?? [],
            'previousMonth' => $month->gt($scheduler->minDate()->startOfMonth()) ? $month->subMonth()->format('Y-m') : null,
            'nextMonth' => $month->lt($scheduler->maxDate()->startOfMonth()) ? $month->addMonth()->format('Y-m') : null,
            'timezone' => $scheduler->timezone(),
            'minDate' => $scheduler->minDate()->toDateString(),
            'maxDate' => $scheduler->maxDate()->toDateString(),
        ]);
    }

    public function store(StoreAppointmentRequest $request, AppointmentScheduler $scheduler, StripeCheckout $checkout): RedirectResponse
    {
        if (! $checkout->configured()) {
            throw ValidationException::withMessages(['payment' => 'Online payments are not configured yet. Please try again once the shop has enabled Stripe.']);
        }

        $appointment = $scheduler->book($request->validated(), $request->user(), true);

        try {
            $url = $checkout->start($appointment);
        } catch (ValidationException $exception) {
            return to_route('appointments.show', $appointment)->withErrors($exception->errors());
        }

        return redirect()->away($url);
    }
}
