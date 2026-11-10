<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AppointmentReminder extends Mailable
{
    public function __construct(public Appointment $appointment) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'A little reminder: your Chair & Blade appointment');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.appointment', with: ['isReminder' => true]);
    }
}
