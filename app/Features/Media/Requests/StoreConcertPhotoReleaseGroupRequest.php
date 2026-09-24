<?php

namespace App\Features\Media\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreConcertPhotoReleaseGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'collection_uuids' => ['required', 'array', 'min:1', 'max:100'],
            'collection_uuids.*' => ['required', 'uuid', 'distinct'],
            'watermark_uuid' => ['nullable', 'uuid'],
        ];
    }
}
