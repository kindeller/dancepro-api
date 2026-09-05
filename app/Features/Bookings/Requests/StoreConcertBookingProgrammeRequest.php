<?php

namespace App\Features\Bookings\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreConcertBookingProgrammeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageScheduling') ?? false;
    }

    public function rules(): array
    {
        return [
            'programme' => ['required', 'file', 'max:20480', 'mimes:pdf', 'mimetypes:application/pdf'],
        ];
    }
}
