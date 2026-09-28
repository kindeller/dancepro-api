<?php

namespace App\Features\Admin\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveStudioGalleryCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageStudios') ?? false;
    }

    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:0'],
            'gallery_code' => ['required', 'string', 'regex:/\A[A-Za-z0-9-]{3,64}\z/'],
        ];
    }
}
