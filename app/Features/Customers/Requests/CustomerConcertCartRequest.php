<?php

namespace App\Features\Customers\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerConcertCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessCustomer() ?? false;
    }

    public function rules(): array
    {
        return ['tab' => ['nullable', Rule::in(['favourites', 'cart'])]];
    }
}
