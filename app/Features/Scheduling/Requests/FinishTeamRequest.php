<?php

namespace App\Features\Scheduling\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FinishTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'actual_finish_at' => ['required', 'date_format:H:i,Y-m-d H:i:s,Y-m-d\TH:i'],
            'optional_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
