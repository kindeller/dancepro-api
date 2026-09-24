<?php

namespace App\Features\Programmes\Requests;

use Illuminate\Validation\Rule;

class PhotoContributionExceptionRequest extends ProgrammeVersionRequest
{
    protected function prepareForValidation(): void
    {
        $reason = $this->input('reason');
        $this->merge(['reason' => is_string($reason) ? trim($reason) : $reason]);
    }

    public function rules(): array
    {
        return [
            'plan_uuid' => ['required', 'uuid'], 'role_uuid' => ['required', 'uuid'],
            'scope_uuid' => ['nullable', 'uuid'],
            'action' => ['required', Rule::in(['waive', 'reinstate'])],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }
}
