<?php

namespace App\Features\Scheduling\Requests;

use App\Features\Scheduling\Support\AvailabilityRoundStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAvailabilityRoundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageScheduling') ?? false;
    }

    public function rules(): array
    {
        return [
            'availability_status' => ['required', Rule::in([AvailabilityRoundStatus::Open->value, AvailabilityRoundStatus::Closed->value])],
            'availability_deadline' => ['nullable', 'required_if:availability_status,open', 'date_format:Y-m-d', 'after_or_equal:today'],
        ];
    }
}
