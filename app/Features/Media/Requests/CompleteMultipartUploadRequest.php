<?php

namespace App\Features\Media\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompleteMultipartUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parts' => ['required', 'array', 'min:1', 'max:10000'],
            'parts.*.part_number' => ['required', 'integer', 'min:1', 'max:10000', 'distinct'],
            'parts.*.etag' => ['required', 'string', 'max:255'],
            'parts.*.checksum_crc64nvme' => ['required', 'string', 'max:128'],
            'checksum_crc64nvme' => ['required', 'string', 'max:128'],
        ];
    }
}
