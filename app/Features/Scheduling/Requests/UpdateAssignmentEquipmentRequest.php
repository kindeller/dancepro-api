<?php

namespace App\Features\Scheduling\Requests;

use App\Features\Scheduling\Support\AssignmentEquipmentItems;
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
            'item_code' => ['required', Rule::in(array_keys(AssignmentEquipmentItems::all()))],
            'is_bringing' => ['required', 'boolean'],
            'is_taking' => ['required', 'boolean'],
            'other_notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
