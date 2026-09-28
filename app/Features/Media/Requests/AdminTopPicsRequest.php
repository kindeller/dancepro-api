<?php

namespace App\Features\Media\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminTopPicsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'in:competition,concert'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
