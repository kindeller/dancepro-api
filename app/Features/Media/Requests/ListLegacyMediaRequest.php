<?php

namespace App\Features\Media\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListLegacyMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['cursor' => ['nullable', 'string', 'max:4096']];
    }
}
