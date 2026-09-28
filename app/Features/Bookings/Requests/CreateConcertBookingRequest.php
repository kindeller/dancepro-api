<?php

namespace App\Features\Bookings\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateConcertBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['studio' => ['nullable', 'uuid'], 'event_type' => ['nullable', 'uuid']];
    }
}
