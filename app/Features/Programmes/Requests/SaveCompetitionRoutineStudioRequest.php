<?php

namespace App\Features\Programmes\Requests;

use Illuminate\Validation\Rule;

class SaveCompetitionRoutineStudioRequest extends ProgrammeVersionRequest
{
    public function rules(): array
    {
        return [
            'item_uuid' => ['required', 'uuid'],
            'studio_uuid' => ['nullable', 'uuid', Rule::exists('studios', 'uuid')->where('status', 'active')->whereNull('deleted_at')],
            'expected_studio_uuid' => ['nullable', 'uuid'],
        ];
    }
}
