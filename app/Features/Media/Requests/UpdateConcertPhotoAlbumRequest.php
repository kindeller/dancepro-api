<?php

namespace App\Features\Media\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateConcertPhotoAlbumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessAdmin() ?? false;
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255'], 'sort_order' => ['required', 'integer', 'min:0', 'max:9999']];
    }
}
