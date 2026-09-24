<?php

namespace App\Features\Media\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMediaWatermarkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_default' => $this->boolean('is_default')]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'gallery_type' => ['required', Rule::in(['competition', 'concert'])],
            'mode' => ['required', Rule::in(['image', 'text'])],
            'text' => ['required_if:mode,text', 'nullable', 'string', 'max:100'],
            'artwork' => ['required_if:mode,image', 'nullable', 'file', 'mimes:png', 'max:10240', 'dimensions:max_width=6000,max_height=6000'],
            'is_default' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'artwork.max' => 'Watermark PNGs can be up to 10 MB.',
            'artwork.dimensions' => 'Watermark PNGs can be up to 6000 × 6000 pixels.',
        ];
    }
}
