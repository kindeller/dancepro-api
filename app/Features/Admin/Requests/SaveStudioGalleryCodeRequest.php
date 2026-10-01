<?php

namespace App\Features\Admin\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveStudioGalleryCodeRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('gallery_code'))) {
            $this->merge(['gallery_code' => mb_strtoupper(trim($this->input('gallery_code')))]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->can('manageStudios') ?? false;
    }

    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:0'],
            'gallery_code' => ['required', 'string', 'regex:/\A[A-Za-z0-9][A-Za-z0-9_-]{2,49}\z/', Rule::unique('studios', 'code')->ignore($this->route('studio'))],
        ];
    }
}
