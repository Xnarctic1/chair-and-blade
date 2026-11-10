<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\StripeCheckout;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;

class ReconcilePayments extends Command
{
    protected $signature = 'payments:reconcile';

    protected $description = 'Verify overdue Stripe checkouts and release only unpaid expired holds';

    public function handle(StripeCheckout $stripe): int
    {
        if (! $stripe->configured()) {
            $this->info('Stripe is not configured; no payment status was changed.');

            return self::SUCCESS;
        }

        $failures = 0;
        Appointment::where('status', 'pending_payment')->where('payment_status', 'unpaid')
            ->where('hold_expires_at', '<=', now())->chunkById(100, function ($appointments) use ($stripe, &$failures): void {
                foreach ($appointments as $appointment) {
                    try {
                        $stripe->reconcile($appointment);
                    } catch (ApiErrorException|LockTimeoutException $exception) {
                        $failures++;
                        Log::warning('Stripe reconciliation deferred; hold retained.', ['appointment_id' => $appointment->id, 'type' => $exception::class]);
                    }
                }
            });

        $this->info($failures ? "Reconciliation finished; {$failures} holds retained for retry." : 'Payment reconciliation complete.');

        return $failures ? self::FAILURE : self::SUCCESS;
    }
}
