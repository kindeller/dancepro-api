<?php

namespace App\Features\Bookings\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviewConcertBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageScheduling') ?? false;
    }

    public function rules(): array
    {
        return ['internal_review_note' => ['nullable', 'string', 'max:5000']];
    }
}
