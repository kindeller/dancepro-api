<?php

namespace App\Features\Chat\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartDirectChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['recipient_profile_uuid' => ['required', 'uuid', 'exists:crew_profiles,uuid']];
    }
}
