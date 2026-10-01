<?php

namespace App\Features\Concerts\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateConcertFamilyShareRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessCustomer() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['recipient_email' => mb_strtolower(trim((string) $this->input('recipient_email')))]);
    }

    public function rules(): array
    {
        return ['recipient_name' => ['required', 'string', 'max:100'], 'recipient_email' => ['required', 'email:rfc', 'max:255'], 'assets' => ['required', 'array', 'min:1', 'max:500'], 'assets.*' => ['required', 'uuid', 'distinct'], 'terms' => ['accepted']];
    }
}
