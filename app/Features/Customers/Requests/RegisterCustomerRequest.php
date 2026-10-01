<?php

namespace App\Features\Customers\Requests;

use App\Shared\Support\AccountName;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() === null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(AccountName::input($this->only(['name', 'first_name', 'last_name'])));
        if (is_string($this->input('email'))) {
            $this->merge(['email' => strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::min(12), function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && strlen($value) > 72) {
                    $fail('Please use a password of no more than 72 bytes.');
                }
            }],
            'policy_accepted' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return ['email.unique' => 'Unable to create an account with this email. Try signing in or resetting your password.'];
    }
}
