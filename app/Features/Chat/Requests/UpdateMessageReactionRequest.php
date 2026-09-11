<?php

namespace App\Features\Chat\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMessageReactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessCrew() ?? false;
    }

    public function rules(): array
    {
        return ['reaction' => ['present', 'nullable', 'in:thumbs_up,heart,laugh,angry']];
    }
}
