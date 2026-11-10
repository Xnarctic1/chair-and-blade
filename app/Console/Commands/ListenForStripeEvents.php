<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class ListenForStripeEvents extends Command
{
    protected $signature = 'payments:listen';

    protected $description = 'Forward local Stripe test events and save the signing secret without displaying it';

    public function handle(): int
    {
        if (! app()->environment('local') || config('payments.mode') !== 'test' || ! str_starts_with((string) config('payments.secret'), 'sk_test_')) {
            $this->error('This local listener requires a Stripe test key in .env.');

            return self::FAILURE;
        }

        $process = new Process(['stripe', 'listen', '--skip-update', '--forward-to', rtrim(config('app.url'), '/').'/stripe/webhook', '--events', 'checkout.session.completed,checkout.session.async_payment_succeeded,checkout.session.expired'], base_path(), ['STRIPE_API_KEY' => config('payments.secret'), 'STRIPE_DEVICE_NAME' => 'chair-and-blade-local']);
        $process->setTimeout(null);
        $this->info('Connecting to Stripe test events. Keep this process running during local checkout testing.');
        $buffers = [];

        return $process->run(function (string $type, string $output) use (&$buffers): void {
            $buffers[$type] = ($buffers[$type] ?? '').$output;
            while (($newline = strpos($buffers[$type], "\n")) !== false) {
                $line = substr($buffers[$type], 0, $newline);
                $buffers[$type] = substr($buffers[$type], $newline + 1);
                if (preg_match('/whsec_[A-Za-z0-9]+/', $line, $matches)) {
                    $path = base_path('.env');
                    $content = file_get_contents($path);
                    $setting = 'STRIPE_WEBHOOK_SECRET='.$matches[0];
                    $content = preg_match('/^STRIPE_WEBHOOK_SECRET=.*$/m', $content)
                        ? preg_replace('/^STRIPE_WEBHOOK_SECRET=.*$/m', $setting, $content)
                        : rtrim($content)."\n".$setting."\n";
                    file_put_contents($path, $content, LOCK_EX);
                    $this->callSilent('config:clear');
                    $this->info('Webhook ready. Signing secret saved in .env.');
                } elseif (str_contains($line, '[200]')) {
                    $this->line('Stripe event received successfully.');
                } elseif (preg_match('/error|failed|fatal/i', $line)) {
                    $this->error('Stripe listener reported a connection or forwarding error. Check the test key, network, and local server.');
                }
            }
        });
    }
}
