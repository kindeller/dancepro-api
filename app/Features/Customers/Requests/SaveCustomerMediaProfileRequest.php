<?php

namespace App\Features\Customers\Requests;

use App\Shared\Support\AccountName;
use Illuminate\Validation\Rule;

class SaveCustomerMediaProfileRequest extends CustomerCompetitionsRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->input('action') === 'details') {
            $this->merge(AccountName::input($this->only(['name', 'first_name', 'last_name'])));
            if (is_string($this->input('email'))) {
                $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
            }
        }
        if (is_string($this->input('programme_names'))) {
            $this->merge(['programme_names' => array_values(array_unique(array_filter(array_map('trim', preg_split('/\R/', $this->input('programme_names'))))))]);
        }
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['details', 'preferences', 'dancer', 'dancer_update', 'dancer_remove', 'studio', 'studio_remove', 'studio_request', 'studio_gallery'])],
            'name' => ['exclude_unless:action,details,dancer,dancer_update', 'required', 'string', 'max:255'],
            'first_name' => ['exclude_unless:action,details', 'required', 'string', 'max:255'],
            'last_name' => ['exclude_unless:action,details', 'required', 'string', 'max:255'],
            'preferred_name' => ['exclude_unless:action,details', 'required', 'string', 'max:100'],
            'phone' => ['exclude_unless:action,details', 'required', 'string', 'max:40'],
            'email' => ['exclude_unless:action,details', 'required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($this->user()?->id)],
            'current_password' => ['exclude_unless:action,details', Rule::excludeIf($this->input('email') === $this->user()?->email), 'required', 'current_password:web'],
            'relationship' => ['exclude_unless:action,dancer,dancer_update', 'sometimes', Rule::in(['unspecified', 'self', 'child', 'other'])],
            'programme_names' => ['exclude_unless:action,dancer,dancer_update', 'nullable', 'array', 'max:10'],
            'programme_names.*' => ['string', 'max:255'],
            'dancer_uuid' => ['exclude_unless:action,dancer_update,dancer_remove', 'required', 'uuid'],
            'studio_uuid' => ['exclude_unless:action,studio,studio_remove,studio_gallery', 'required', 'uuid',
                Rule::when(in_array($this->input('action'), ['studio', 'studio_gallery']), Rule::exists('studios', 'uuid')->where('status', 'active')->whereNull('deleted_at'))],
            'studio_code' => ['exclude_unless:action,studio', 'required', 'string', 'max:50'],
            'studio_name' => ['exclude_unless:action,studio_request', 'required', 'string', 'max:255'],
            'studio_location' => ['exclude_unless:action,studio_request', 'nullable', 'string', 'max:255'],
            'studio_gallery_code' => ['exclude_unless:action,studio_gallery', 'required', 'string', 'max:64'],
            'media_ready_email' => ['exclude_unless:action,preferences', 'required', 'boolean'],
            'expiry_reminders_email' => ['exclude_unless:action,preferences', 'required', 'boolean'],
            'marketing_email' => ['exclude_unless:action,preferences', 'required', 'boolean'],
        ];
    }
}
