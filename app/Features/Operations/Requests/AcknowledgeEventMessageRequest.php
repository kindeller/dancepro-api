<?php

namespace App\Features\Operations\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AcknowledgeEventMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}
