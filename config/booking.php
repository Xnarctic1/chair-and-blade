<?php

return [
    'timezone' => 'Europe/Malta',
    'days_ahead' => 60,
    'slot_minutes' => 15,
    'minimum_notice_minutes' => 45,
    'hold_minutes' => 35,
    'emails_enabled' => env('APPOINTMENT_EMAILS_ENABLED', false),
    'reminder_hours' => 24,
    'opening_hours' => [
        2 => [['09:00', '13:00'], ['14:00', '18:00']],
        3 => [['09:00', '13:00'], ['14:00', '18:00']],
        4 => [['09:00', '13:00'], ['14:00', '18:00']],
        5 => [['09:00', '13:00'], ['14:00', '18:00']],
        6 => [['09:00', '13:00'], ['14:00', '18:00']],
    ],
];
