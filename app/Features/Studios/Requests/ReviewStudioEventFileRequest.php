<?php

namespace App\Features\Studios\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviewStudioEventFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageScheduling') ?? false;
    }

    public function rules(): array
    {
        return ['status' => ['required', 'in:approved,needs_attention'], 'review_note' => ['nullable', 'string', 'max:2000']];
    }
}
