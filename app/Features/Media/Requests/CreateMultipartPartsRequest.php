<?php

namespace App\Features\Media\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateMultipartPartsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parts' => ['required', 'array', 'min:1', 'max:'.config('media.max_parts_per_request')],
            'parts.*.part_number' => ['required', 'integer', 'min:1', 'max:10000', 'distinct'],
            'parts.*.checksum_crc64nvme' => ['required', 'string', 'max:128'],
        ];
    }
}
