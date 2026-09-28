<?php

namespace App\Features\Studios\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudioBookingChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessCustomer() ?? false;
    }

    public function rules(): array
    {
        return [
            'change_type' => ['nullable', Rule::in(['date', 'times', 'venue', 'services', 'contact', 'other'])],
            'requested_value' => ['nullable', 'string', 'max:2000', 'required_with:change_type'],
            'details' => ['nullable', 'string', 'max:5000'],
            'booking' => ['nullable', 'array', 'required_without:change_type'],
            'booking.event_date' => ['nullable', 'date'],
            'booking.venue_name' => ['nullable', 'string', 'max:255'],
            'booking.starts_at' => ['nullable', 'date_format:H:i'],
            'booking.finishes_at' => ['nullable', 'date_format:H:i'],
            'booking.access_time' => ['nullable', 'date_format:H:i'],
            'booking.services' => ['nullable', 'array'],
            'booking.services.*' => ['string', Rule::in(['photography', 'videography', 'portraits'])],
            'booking.services_present' => ['nullable', 'boolean'],
        ];
    }
}
