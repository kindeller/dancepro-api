<?php

namespace App\Features\Media\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FinalizeMediaAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'expected_outputs' => ['required', 'array', 'min:2'],
            'expected_outputs.*' => ['string', 'distinct', 'in:original,fallback_mp4,hls_720p,hls_480p,poster'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            foreach (['original', 'fallback_mp4'] as $required) {
                if (! in_array($required, $this->array('expected_outputs'), true)) {
                    $validator->errors()->add('expected_outputs', "The {$required} output is required.");
                }
            }
        }];
    }
}
