<?php

namespace App\Console\Commands;

use App\Mail\AppointmentConfirmation;
use App\Mail\AppointmentReminder;
use App\Models\Appointment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendAppointmentEmails extends Command
{
    protected $signature = 'appointments:send-emails';

    protected $description = 'Send paid appointment confirmations and reminders due within 24 hours';

    public function handle(): int
    {
        if (! config('booking.emails_enabled')) {
            $this->info('Appointment emails are disabled. Configure a mail provider and APPOINTMENT_EMAILS_ENABLED first.');

            return self::SUCCESS;
        }

        $failed = 0;
        $sent = 0;
        Appointment::where('status', 'confirmed')->where('payment_status', 'paid')->where('starts_at', '>', now())
            ->where(function ($query): void {
                $query->whereNull('confirmation_sent_at')->orWhere(function ($query): void {
                    $query->whereNull('reminder_sent_at')->where('starts_at', '<=', now()->addHours(config('booking.reminder_hours')));
                });
            })->eachById(function (Appointment $appointment) use (&$failed, &$sent): void {
                try {
                    Cache::lock('appointment-email:'.$appointment->id, 120)->get(function () use ($appointment, &$sent): void {
                        $appointment->refresh()->load('service');
                        if ($appointment->status !== 'confirmed' || $appointment->payment_status !== 'paid' || ! $appointment->starts_at->isFuture()) {
                            return;
                        }
                        $reminderDue = $appointment->starts_at->lte(now()->addHours(config('booking.reminder_hours')));
                        if (! $appointment->confirmation_sent_at) {
                            Mail::to($appointment->email)->send(new AppointmentConfirmation($appointment));
                            $appointment->forceFill(['confirmation_sent_at' => now(), 'reminder_sent_at' => $reminderDue ? now() : null])->save();
                            $sent++;
                        } elseif ($reminderDue && ! $appointment->reminder_sent_at) {
                            Mail::to($appointment->email)->send(new AppointmentReminder($appointment));
                            $appointment->forceFill(['reminder_sent_at' => now()])->save();
                            $sent++;
                        }
                    });
                } catch (Throwable $exception) {
                    report($exception);
                    $this->error('Email delivery failed for appointment '.$appointment->reference.'. It will be retried on the next run.');
                    $failed++;
                }
            });
        $this->info('Appointment emails sent: '.$sent);

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
