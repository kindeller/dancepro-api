<?php

namespace App\Features\Website\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveWebsiteBiographyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessAdmin() ?? false;
    }

    public function rules(): array
    {
        return ['action' => ['required', 'in:draft,publish,hide'], 'name' => ['required', 'string', 'max:120'], 'role' => ['nullable', 'string', 'max:120'], 'body' => ['required', 'string', 'max:12000'], 'sort_order' => ['required', 'integer', 'min:0', 'max:9999'], 'source_hash' => ['required', 'string', 'size:64'], 'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'], 'remove_image' => ['sometimes', 'boolean']];
    }
}
