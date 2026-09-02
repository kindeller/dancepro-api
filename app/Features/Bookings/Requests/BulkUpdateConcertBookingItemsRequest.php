<?php

namespace App\Features\Bookings\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUpdateConcertBookingItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageScheduling') ?? false;
    }

    public function rules(): array
    {
        return [
            'event_ids' => ['required', 'array', 'min:1'],
            'event_ids.*' => ['required', 'uuid', 'distinct', 'exists:concert_booking_items,uuid'],
            'action' => ['required', Rule::in(['approve', 'open', 'close'])],
            'deadline_date' => ['nullable', 'required_if:action,open', 'date_format:Y-m-d'],
        ];
    }
}
