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
        return ['confirm_send' => ['accepted']];
    }
}
