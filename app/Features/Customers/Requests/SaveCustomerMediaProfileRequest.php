<?php

namespace App\Features\Customers\Requests;

use Illuminate\Validation\Rule;

class SaveCustomerMediaProfileRequest extends CustomerCompetitionsRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('programme_names'))) {
            $this->merge(['programme_names' => array_values(array_unique(array_filter(array_map('trim', preg_split('/\R/', $this->input('programme_names'))))))]);
        }
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['details', 'preferences', 'dancer', 'dancer_update', 'dancer_remove', 'studio', 'studio_remove'])],
            'name' => ['exclude_unless:action,details,dancer,dancer_update', 'required', 'string', 'max:255'],
            'preferred_name' => ['exclude_unless:action,details', 'nullable', 'string', 'max:100'],
            'phone' => ['exclude_unless:action,details', 'nullable', 'string', 'max:40'],
            'relationship' => ['exclude_unless:action,dancer,dancer_update', 'sometimes', Rule::in(['unspecified', 'self', 'child', 'other'])],
            'programme_names' => ['exclude_unless:action,dancer,dancer_update', 'nullable', 'array', 'max:10'],
            'programme_names.*' => ['string', 'max:255'],
            'dancer_uuid' => ['exclude_unless:action,dancer_update,dancer_remove', 'required', 'uuid'],
            'studio_uuid' => ['exclude_unless:action,studio,studio_remove', 'required', 'uuid',
                Rule::when($this->input('action') === 'studio', Rule::exists('studios', 'uuid')->where('status', 'active')->whereNull('deleted_at'))],
            'studio_gallery_code' => ['exclude_unless:action,studio', 'required', 'string', 'max:64'],
            'media_ready_email' => ['exclude_unless:action,preferences', 'required', 'boolean'],
            'expiry_reminders_email' => ['exclude_unless:action,preferences', 'required', 'boolean'],
            'marketing_email' => ['exclude_unless:action,preferences', 'required', 'boolean'],
        ];
    }
}
