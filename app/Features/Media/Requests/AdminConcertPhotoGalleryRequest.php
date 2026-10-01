<?php

namespace App\Features\Media\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminConcertPhotoGalleryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessAdmin() ?? false;
    }

    public function rules(): array
    {
        return ['album' => ['sometimes', 'uuid'], 'page' => ['sometimes', 'integer', 'min:1'], 'studio' => ['sometimes', 'uuid', 'exists:studios,uuid', 'prohibits:unlinked'], 'unlinked' => ['sometimes', 'boolean', 'prohibits:studio'], 'photo' => ['sometimes', 'uuid'], 'size' => ['sometimes', 'in:thumbnail,preview,cover']];
    }
}
