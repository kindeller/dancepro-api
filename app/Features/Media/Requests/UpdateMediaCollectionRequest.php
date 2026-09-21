<?php

namespace App\Features\Media\Requests;

use App\Features\Media\Support\MediaCollectionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMediaCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'status' => ['sometimes', Rule::in([MediaCollectionStatus::Draft->value, MediaCollectionStatus::Published->value])],
        ];
    }
}
