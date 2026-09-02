<?php

namespace App\Features\Bookings\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewConcertBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageScheduling') ?? false;
    }

    public function rules(): array
    {
        return [
            'internal_review_note' => ['nullable', 'string', 'max:5000'],
            'studio_uuid' => ['nullable', 'uuid', Rule::exists('studios', 'uuid')->whereNull('deleted_at')],
        ];
    }
}
