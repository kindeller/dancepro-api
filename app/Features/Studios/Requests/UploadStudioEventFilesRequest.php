<?php

namespace App\Features\Studios\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadStudioEventFilesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessCustomer() ?? false;
    }

    public function rules(): array
    {
        return [
            'kind' => ['required', 'in:programme,studio_video'],
            'files' => ['required', 'array', 'min:1', 'max:20'],
            'files.*' => ['required', 'file', 'max:2097152'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            if ($this->input('kind') === 'programme' && count($this->file('files', [])) !== 1) {
                $validator->errors()->add('files', 'Upload one programme file at a time.');
            }
        }];
    }
}
