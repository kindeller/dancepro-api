<?php

namespace App\Features\Customers\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GuestGalleryUnlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => is_string($this->input('email')) ? mb_strtolower(trim($this->input('email'))) : $this->input('email'),
            'login_code' => is_string($this->input('login_code')) ? strtoupper(trim($this->input('login_code'))) : $this->input('login_code'),
        ]);
    }

    public function rules(): array
    {
        return ['email' => ['required', 'email', 'max:255'], 'login_code' => ['required', 'string', 'max:64']];
    }
}
