<?php

namespace App\Features\Scheduling\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateSchedulingShiftTimesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageScheduling') ?? false;
    }

    public function rules(): array
    {
        return ['start_time' => ['required', 'date_format:H:i'], 'finish_time' => ['required', 'date_format:H:i']];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->input('finish_time') <= $this->input('start_time')) {
                $validator->errors()->add('finish_time', 'Finish time must be after the start time.');
            }
        }];
    }
}
