<?php

namespace App\Features\Media\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMediaAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'media_type' => ['required', 'in:video'],
            'display_name' => ['required', 'string', 'max:255'],
            'original_filename' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'expected_outputs' => ['required', 'array', 'min:2'],
            'expected_outputs.*' => ['string', 'distinct', 'in:original,fallback_mp4,hls_720p,hls_480p,poster'],
            'source' => ['nullable', 'array'],
            'source.duration_seconds' => ['nullable', 'integer', 'min:0'],
            'source.width' => ['nullable', 'integer', 'min:1'],
            'source.height' => ['nullable', 'integer', 'min:1'],
            'source.fallback_filename' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            $outputs = $this->array('expected_outputs');
            foreach (['original', 'fallback_mp4'] as $required) {
                if (! in_array($required, $outputs, true)) {
                    $validator->errors()->add('expected_outputs', "The {$required} output is required.");
                }
            }
        }];
    }
}
