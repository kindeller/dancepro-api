<?php

namespace App\Features\Media\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LocalMediaUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app()->environment('local') && $this->hasValidSignature();
    }

    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:1024'],
            'checksum' => ['required', 'string', 'max:128'],
        ];
    }
}
