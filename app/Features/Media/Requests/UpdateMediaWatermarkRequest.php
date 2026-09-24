<?php

namespace App\Features\Media\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMediaWatermarkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessAdmin() ?? false;
    }

    public function rules(): array
    {
        return ['action' => ['required', 'in:default,archive']];
    }
}
