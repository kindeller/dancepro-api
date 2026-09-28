<?php

namespace App\Features\Customers\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReplyCustomerConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->routeIs('admin.*') ? ($this->user()?->canAccessAdmin() ?? false) : ($this->user()?->canAccessCustomer() ?? false);
    }

    public function rules(): array
    {
        return ['message' => ['required', 'string', 'max:5000'], 'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf,txt', 'max:10240']];
    }
}
