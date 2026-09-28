<?php

namespace App\Features\Customers\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerAccountNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessAdmin() ?? false;
    }

    public function rules(): array
    {
        return ['note' => ['required', 'string', 'max:5000'], 'tags' => ['nullable', 'string', 'max:500'], 'follow_up_at' => ['nullable', 'date']];
    }
}
