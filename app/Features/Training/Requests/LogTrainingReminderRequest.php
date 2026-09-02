<?php

namespace App\Features\Training\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LogTrainingReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageCrew') ?? false;
    }

    public function rules(): array
    {
        return [
            'method' => ['required', Rule::in(['manual', 'email', 'phone', 'in_person'])],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
