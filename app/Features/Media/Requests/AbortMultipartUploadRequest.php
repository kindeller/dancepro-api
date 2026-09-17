<?php

namespace App\Features\Media\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AbortMultipartUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}
