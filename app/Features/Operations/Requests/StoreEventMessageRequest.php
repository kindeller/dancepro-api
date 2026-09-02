<?php

namespace App\Features\Operations\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'message_type' => ['required', Rule::in(['discussion', 'announcement'])],
            'body' => ['nullable', 'string', 'max:10000', 'required_without:attachment'],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,csv,txt'],
        ];
    }
}
