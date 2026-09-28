<?php

namespace App\Features\Customers\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendCustomerAccountEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessAdmin() ?? false;
    }

    public function rules(): array
    {
        return ['subject' => ['required', 'string', 'max:160'], 'body' => ['required', 'string', 'max:10000']];
    }
}
