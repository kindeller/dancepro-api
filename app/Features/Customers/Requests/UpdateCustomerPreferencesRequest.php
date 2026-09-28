<?php

namespace App\Features\Customers\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessAdmin() ?? false;
    }

    public function rules(): array
    {
        return ['media_ready_email' => ['sometimes', 'boolean'], 'expiry_reminders_email' => ['sometimes', 'boolean'], 'marketing_email' => ['sometimes', 'boolean'], 'service_email' => ['sometimes', 'boolean'], 'download_email' => ['sometimes', 'boolean'], 'gallery_email' => ['sometimes', 'boolean'], 'reason' => ['required', 'string', 'max:500']];
    }
}
