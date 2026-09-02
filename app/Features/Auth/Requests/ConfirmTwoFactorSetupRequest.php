<?php

namespace App\Features\Auth\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmTwoFactorSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && config('security.two_factor.enabled');
    }

    public function rules(): array
    {
        return ['code' => ['required', 'digits:6']];
    }
}
