<?php

namespace App\Features\Publicity\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GallerySharingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessAdmin() ?? false;
    }

    public function rules(): array
    {
        return ['type' => ['sometimes', 'in:competition,studio'], 'q' => ['nullable', 'string', 'max:150'], 'page' => ['sometimes', 'integer', 'min:1']];
    }
}
