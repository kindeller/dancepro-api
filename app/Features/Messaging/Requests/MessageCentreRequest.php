<?php

namespace App\Features\Messaging\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MessageCentreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'channel' => ['sometimes', Rule::in(['all', 'customer', 'studio', 'crew'])],
            'mode' => ['sometimes', Rule::in(['inbox', 'new'])],
            'q' => ['nullable', 'string', 'max:200'],
            'status' => ['sometimes', Rule::in(['all', 'pending', 'approved', 'declined', 'resolved', 'unread'])],
            'thread' => ['nullable', 'uuid'],
            'recipient' => ['nullable', 'uuid'],
        ];
    }
}
