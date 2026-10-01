<?php

namespace App\Features\Messaging\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendCentreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'channel' => ['required', Rule::in(['customer', 'studio', 'crew'])],
            'thread' => ['nullable', 'uuid', 'required_without:recipient'],
            'recipient' => ['nullable', 'uuid', 'required_without:thread'],
            'subject' => ['nullable', 'required_without:thread', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'recipient_email' => ['nullable', 'email', 'max:255'],
            'event_uuid' => ['nullable', 'uuid'],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,csv,txt'],
        ];
    }
}
