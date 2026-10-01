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
            'event_uuids' => ['required_without:collection_uuids', 'prohibits:collection_uuids', 'array', 'min:1', 'max:100'],
            'event_uuids.*' => ['required', 'uuid', 'distinct'],
            'collection_uuids' => ['required_without:event_uuids', 'prohibits:event_uuids', 'array', 'min:1', 'max:100'],
            'collection_uuids.*' => ['required', 'uuid', 'distinct'],
            'watermark_uuid' => ['nullable', 'uuid'],
        ];
    }
}
