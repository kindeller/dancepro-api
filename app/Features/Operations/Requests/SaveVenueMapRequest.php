<?php

namespace App\Features\Operations\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveVenueMapRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageScheduling') ?? false;
    }

    public function rules(): array
    {
        return ['map' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:20480']];
    }
}
