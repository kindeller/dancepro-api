<?php

namespace App\Features\Scheduling\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEventCrewAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageScheduling') ?? false;
    }

    public function rules(): array
    {
        return ['crew_profile_uuid' => ['nullable', 'uuid', 'exists:crew_profiles,uuid']];
    }
}
