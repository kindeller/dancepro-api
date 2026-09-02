<?php

namespace App\Features\Scheduling\Requests;

use App\Features\Scheduling\Support\AvailabilityResponseStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordAvailabilityResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active && $this->user()?->crewProfile !== null;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(AvailabilityResponseStatus::class)],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
