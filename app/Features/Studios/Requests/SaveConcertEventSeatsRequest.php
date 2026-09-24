<?php

namespace App\Features\Studios\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveConcertEventSeatsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessCustomer() ?? false;
    }

    public function rules(): array
    {
        return [
            'roles' => ['required', 'array'],
            'roles.*' => ['array', 'max:6'],
            'roles.*.*.row' => ['nullable', 'string', 'max:30'],
            'roles.*.*.seat' => ['nullable', 'string', 'max:30'],
        ];
    }
}
