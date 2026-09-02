<?php

namespace App\Features\Scheduling\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminAvailabilityResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageScheduling') ?? false;
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::in(['available', 'unavailable', 'unanswered'])]];
    }
}
