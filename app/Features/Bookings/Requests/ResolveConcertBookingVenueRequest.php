<?php

namespace App\Features\Bookings\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveConcertBookingVenueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageScheduling') ?? false;
    }

    public function rules(): array
    {
        return [
            'resolution_action' => ['required', Rule::in(['match', 'create'])],
            'venue_uuid' => ['nullable', 'required_if:resolution_action,match', 'uuid', 'exists:venues,uuid'],
        ];
    }
}
