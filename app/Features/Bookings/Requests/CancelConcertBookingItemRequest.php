<?php

namespace App\Features\Bookings\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelConcertBookingItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageScheduling') ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
