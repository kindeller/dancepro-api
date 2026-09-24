<?php

namespace App\Features\Tracking\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendStudioConversationMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:10000'],
            'subject' => ['nullable', 'string', 'max:255'],
            'event_uuid' => ['nullable', 'uuid'],
            'recipient_email' => ['nullable', 'email', 'max:255'],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,csv,txt'],
        ];
    }
}
