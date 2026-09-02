<?php

namespace App\Features\Chat\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['body' => ['required', 'string', 'max:5000']];
    }
}
