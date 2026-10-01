<?php

namespace App\Features\Website\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WebsiteEventVisibilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessAdmin() ?? false;
    }

    public function rules(): array
    {
        return ['website_visible' => ['required', 'boolean']];
    }
}
