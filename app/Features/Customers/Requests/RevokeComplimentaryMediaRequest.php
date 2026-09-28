<?php

namespace App\Features\Customers\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RevokeComplimentaryMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
            'notify_customer' => ['sometimes', 'boolean'],
        ];
    }
}
