<?php

namespace App\Features\Customers\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerFollowUpsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['scope' => ['nullable', Rule::in(['due', 'upcoming', 'completed'])], 'page' => ['nullable', 'integer', 'min:1']];
    }
}
