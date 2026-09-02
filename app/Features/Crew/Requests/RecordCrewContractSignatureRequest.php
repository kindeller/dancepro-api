<?php

namespace App\Features\Crew\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordCrewContractSignatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageCrew') ?? false;
    }

    public function rules(): array
    {
        return [
            'signed_at' => ['required', 'date', 'before_or_equal:now'],
            'recording_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
