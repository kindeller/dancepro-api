<?php

namespace App\Features\Venues\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResolveAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessCrew() ?? false;
    }

    public function rules(): array
    {
        return ['placeId' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_-]+$/']];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['placeId' => $this->route('placeId')]);
    }
}
