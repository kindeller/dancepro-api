<?php

namespace App\Features\Admin\Requests;

use Illuminate\Validation\Rule;

class ReviewCustomerStudioRequest extends SaveStudioRequest
{
    public function rules(): array
    {
        $rules = [
            'decision' => ['required', Rule::in(['create', 'match', 'decline'])],
            'review_note' => ['required_if:decision,decline', 'nullable', 'string', 'max:2000'],
            'studio_uuid' => ['exclude_unless:decision,match', 'required', 'uuid', Rule::exists('studios', 'uuid')->where('status', 'active')->whereNull('deleted_at')],
        ];
        if ($this->input('decision') === 'create') {
            $rules += parent::rules();
            $rules['status'] = ['required', Rule::in(['active'])];
        }

        return $rules;
    }
}
