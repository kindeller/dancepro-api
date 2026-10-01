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
            'studio_invoice_uuid' => [$this->route('taskKey') === 'invoice_sent' ? 'required' : 'nullable', 'uuid'],
            'confirm_resend' => ['sometimes', 'accepted'],
            'previous_delivery_id' => ['nullable', 'integer'],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'recipient_email' => ['required', 'email:rfc', 'max:255'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
            'email_subject' => ['sometimes', 'required', 'string', 'max:200', 'not_regex:/[\r\n]/'],
            'email_body' => ['sometimes', 'required', 'string', 'max:20000'],
        ];
    }
}
