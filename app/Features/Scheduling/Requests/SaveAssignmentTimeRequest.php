<?php

namespace App\Features\Scheduling\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveAssignmentTimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'actual_clock_in_at' => ['nullable', 'date_format:H:i,Y-m-d H:i:s,Y-m-d\TH:i'],
            'actual_finish_at' => ['nullable', 'date_format:H:i,Y-m-d H:i:s,Y-m-d\TH:i', 'after_or_equal:actual_clock_in_at'],
            'optional_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
