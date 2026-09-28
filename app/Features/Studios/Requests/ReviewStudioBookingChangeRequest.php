<?php

namespace App\Features\Studios\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewStudioBookingChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageScheduling') ?? false;
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::in(['requested', 'in_review', 'declined'])]];
    }
}
