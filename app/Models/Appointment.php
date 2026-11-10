<?php

namespace App\Models;

use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    protected $fillable = ['reference', 'service_id', 'name', 'email', 'starts_at', 'ends_at', 'duration_minutes', 'price_cents', 'status', 'user_id', 'payment_status', 'checkout_session_id', 'checkout_url', 'payment_intent_id', 'hold_expires_at', 'paid_at', 'confirmation_sent_at', 'reminder_sent_at'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'hold_expires_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
            'confirmation_sent_at' => 'immutable_datetime',
            'reminder_sent_at' => 'immutable_datetime',
            'duration_minutes' => 'integer',
            'price_cents' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
