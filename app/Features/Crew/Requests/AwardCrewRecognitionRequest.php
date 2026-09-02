<?php

namespace App\Features\Crew\Requests;

use App\Features\Crew\Support\RecognitionBadgeDesigns;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AwardCrewRecognitionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['show_on_profile' => $this->boolean('show_on_profile')]);
    }

    public function authorize(): bool
    {
        return $this->user()?->can('manageCrew') ?? false;
    }

    public function rules(): array
    {
        return [
            'recognition_type_id' => ['nullable', 'integer', 'exists:recognition_types,id'],
            'crew_profile_ids' => ['required', 'array', 'min:1', 'max:100'],
            'crew_profile_ids.*' => ['integer', 'distinct', 'exists:crew_profiles,id'],
            'scheduling_event_id' => ['nullable', 'integer', 'exists:scheduling_events,id'],
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
            'icon' => ['required', 'string', 'max:20'],
            'design' => ['required', Rule::in(array_keys(RecognitionBadgeDesigns::options()))],
            'awarded_on' => ['required', 'date'],
            'show_on_profile' => ['required', 'boolean'],
        ];
    }
}
