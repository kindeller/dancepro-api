<?php

namespace App\Features\Customers\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerMessagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessCustomer() ?? false;
    }

    public function rules(): array
    {
        return ['tab' => ['sometimes', Rule::in(['notifications', 'conversations', 'new'])], 'page' => ['sometimes', 'integer', 'min:1']];
    }
}
