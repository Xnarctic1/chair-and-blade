<?php

namespace App\Http\Requests;

use App\AppointmentScheduler;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAppointmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $scheduler = app(AppointmentScheduler::class);

        return [
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$scheduler->minDate()->toDateString(), 'before_or_equal:'.$scheduler->maxDate()->toDateString()],
            'time' => ['required', 'date_format:H:i'],
        ];
    }
}
