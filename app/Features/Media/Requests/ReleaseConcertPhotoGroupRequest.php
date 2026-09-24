<?php

namespace App\Features\Media\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReleaseConcertPhotoGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessAdmin() ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
