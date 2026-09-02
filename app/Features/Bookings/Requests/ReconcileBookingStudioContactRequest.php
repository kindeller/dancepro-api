<?php

namespace App\Features\Bookings\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReconcileBookingStudioContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageScheduling') ?? false;
    }

    public function rules(): array
    {
        return [
            'studio_uuid' => ['required', 'uuid', Rule::exists('studios', 'uuid')->whereNull('deleted_at')],
            'action' => ['required', Rule::in(['update', 'add'])],
            'fields' => ['nullable', 'array'],
            'fields.*' => [Rule::in(['studio_name', 'name', 'email', 'phone', 'role'])],
            'role' => ['nullable', 'string', 'max:255'],
        ];
    }
}
