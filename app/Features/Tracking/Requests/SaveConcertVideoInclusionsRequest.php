<?php

namespace App\Features\Tracking\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveConcertVideoInclusionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageScheduling') && ! $this->route('schedulingEvent')->cancelled_at;
    }

    public function rules(): array
    {
        return ['batch_uuid' => ['required', 'uuid'], 'inclusions' => ['sometimes', 'array'], 'inclusions.*' => ['nullable', 'uuid']];
    }
}
