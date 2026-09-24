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
        return ['photo' => ['sometimes', 'uuid'], 'size' => ['sometimes', 'in:thumbnail,preview']];
    }
}
