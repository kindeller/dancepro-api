<?php

namespace App\Features\Chat\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarkChatReadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessCrew() ?? false;
    }

    public function rules(): array
    {
        return ['through_message' => ['required', 'uuid']];
    }
}
