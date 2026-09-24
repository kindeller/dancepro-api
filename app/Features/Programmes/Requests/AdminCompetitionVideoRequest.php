<?php

namespace App\Features\Programmes\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminCompetitionVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessAdmin() ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
