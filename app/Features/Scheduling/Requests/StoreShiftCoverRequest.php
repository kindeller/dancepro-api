<?php

namespace App\Features\Scheduling\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreShiftCoverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessCrew() ?? false;
    }

    public function rules(): array
    {
        return [
            'recipients' => ['required', 'array', 'min:1'],
            'recipients.*' => ['required', 'uuid', 'distinct', 'exists:crew_profiles,uuid'],
            'message' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
