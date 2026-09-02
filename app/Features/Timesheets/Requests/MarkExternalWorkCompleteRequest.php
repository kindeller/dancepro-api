<?php

namespace App\Features\Timesheets\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarkExternalWorkCompleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->crewProfile !== null;
    }

    public function rules(): array
    {
        return ['entry_ids' => ['required', 'array', 'min:1'], 'entry_ids.*' => ['required', 'integer']];
    }
}
