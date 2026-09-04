<?php

namespace App\Features\Auth\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ManageMobileTwoFactorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && config('security.two_factor.enabled');
    }

    public function rules(): array
    {
        return ['password' => ['required', 'current_password']];
    }
}
