<?php

namespace App\Features\Scheduling\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssignmentEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageScheduling') ?? false;
    }

    public function rules(): array
    {
        return [
            'item_code' => ['required', Rule::in(['video_1', 'video_2', 'video_3', 'backdrop_1', 'backdrop_2', 'media'])],
            'is_bringing' => ['required', 'boolean'],
            'is_taking' => ['required', 'boolean'],
            'other_notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
