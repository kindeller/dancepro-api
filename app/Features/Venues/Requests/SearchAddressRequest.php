<?php

namespace App\Features\Venues\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessCrew() ?? false;
    }

    public function rules(): array
    {
        return ['q' => ['required', 'string', 'min:3', 'max:200']];
    }
}
