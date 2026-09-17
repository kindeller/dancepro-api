<?php

namespace App\Features\Media\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMediaCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'media_type' => ['required', 'in:video'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
