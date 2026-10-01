<?php

namespace App\Features\Tracking\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendPhotoReleaseEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageScheduling') ?? false;
    }

    public function rules(): array
    {
        return [
            'guest_email_subject' => ['sometimes', 'required', 'string', 'max:200', 'not_regex:/[\r\n]/'],
            'guest_email_body' => ['sometimes', 'required', 'string', 'max:20000'],
            'studio_email_subject' => ['sometimes', 'required', 'string', 'max:200', 'not_regex:/[\r\n]/'],
            'studio_email_body' => ['sometimes', 'required', 'string', 'max:20000'],
            'confirm_send' => ['accepted'],
            'email_subject' => ['sometimes', 'required', 'string', 'max:200', 'not_regex:/[\r\n]/'],
            'email_body' => ['sometimes', 'required', 'string', 'max:20000'],
        ];
    }
}
