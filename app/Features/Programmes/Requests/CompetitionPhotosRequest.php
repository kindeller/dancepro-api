<?php

namespace App\Features\Programmes\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompetitionPhotosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessAdmin() ?? false;
    }

    public function rules(): array
    {
        return ['page' => ['nullable', 'integer', 'min:1']];
    }
}
