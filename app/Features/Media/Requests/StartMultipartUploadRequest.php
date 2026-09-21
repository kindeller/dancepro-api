<?php

namespace App\Features\Media\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartMultipartUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'relative_path' => ['required', 'in:original/video.mp4,stream/fallback.mp4'],
            'content_type' => ['required', 'in:video/mp4'],
            'size_bytes' => ['required', 'integer', 'min:1', 'max:'.config('media.max_object_bytes')],
            'checksum_algorithm' => ['required', 'in:CRC64NVME'],
            'checksum' => ['required', 'string', 'max:128'],
            'source_filename' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            $name = $this->input('source_filename');
            if (is_string($name) && (str_contains($name, '/') || str_contains($name, '\\') || preg_match('/[\x00-\x1F\x7F]/', $name))) {
                $validator->errors()->add('source_filename', 'Use a filename without path separators or control characters.');
            }
        }];
    }
}
