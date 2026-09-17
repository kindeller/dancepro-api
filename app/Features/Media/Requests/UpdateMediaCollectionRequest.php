<?php

namespace App\Features\Media\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMediaCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['name' => ['sometimes', 'string', 'max:255'], 'sort_order' => ['sometimes', 'integer', 'min:0']];
    }
}
