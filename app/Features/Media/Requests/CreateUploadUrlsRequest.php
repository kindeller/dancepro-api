<?php

namespace App\Features\Media\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateUploadUrlsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxFiles = (int) config('media.max_files_per_batch');
        $maxBytes = (int) config('media.max_single_upload_bytes');

        return [
            'files' => ['required', 'array', 'min:1', 'max:'.$maxFiles],
            'files.*.relative_path' => ['required', 'string', 'max:1024', 'distinct'],
            'files.*.content_type' => ['required', 'string', 'max:255'],
            'files.*.size_bytes' => ['required', 'integer', 'min:1', 'max:'.$maxBytes],
            'files.*.checksum_sha256' => ['required', 'string', 'max:128'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            $total = collect($this->array('files'))->sum(fn (mixed $file): int => (int) data_get($file, 'size_bytes', 0));
            if ($total > (int) config('media.max_batch_bytes')) {
                $validator->errors()->add('files', 'The total declared batch size exceeds the server policy.');
            }
        }];
    }
}
