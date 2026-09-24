<?php

namespace App\Features\Customers\Requests;

class RegisterCustomerCompetitionRequest extends CustomerCompetitionsRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('login_code'))) {
            $this->merge(['login_code' => strtoupper(trim($this->input('login_code')))]);
        }
    }

    public function rules(): array
    {
        return ['event_uuid' => ['required', 'uuid'], 'login_code' => ['required', 'string', 'max:64']];
    }
}
