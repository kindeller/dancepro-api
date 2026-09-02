<?php

namespace App\Features\Venues\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVenueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageScheduling') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'suburb' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:50'],
            'postcode' => ['nullable', 'string', 'max:20'],
            'access_notes' => ['nullable', 'string', 'max:5000'],
            'parking_notes' => ['nullable', 'string', 'max:5000'],
            'operational_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
