<?php

namespace App\Features\Admin\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveStudioAccountAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessAdmin() ?? false;
    }

    public function rules(): array
    {
        return ['email' => ['required', 'email', 'max:255'], 'role' => ['required', Rule::in(['owner', 'director', 'office_contact'])]];
    }
}
