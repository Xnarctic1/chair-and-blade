<?php

return [
    'mode' => env('STRIPE_MODE', 'test'),
    'secret' => env('STRIPE_SECRET'),
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    'currency' => 'eur',
];
