<?php

namespace App\Features\Media\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportLegacyMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'object_ref' => ['required', 'string', 'max:8192'],
            'display_name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_visible' => ['nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            if ($this->boolean('is_visible')) {
                $validator->errors()->add('is_visible', 'Imported assets must remain hidden until reviewed.');
            }
        }];
    }
}
