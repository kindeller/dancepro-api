<?php

namespace App\Features\Payments\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendMediaNotificationTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageScheduling') ?? false;
    }

    public function rules(): array
    {
        return [
            'template' => ['required', Rule::in(['receipt', 'ready', 'expiry'])],
            'email' => ['required', 'email:rfc', 'max:254'],
        ];
    }
}
