<?php

namespace App\Features\Tracking\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendConcertTrackerEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'recipient_email' => ['required', 'email:rfc', 'max:255'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
