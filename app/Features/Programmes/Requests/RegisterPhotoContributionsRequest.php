<?php

namespace App\Features\Programmes\Requests;

class RegisterPhotoContributionsRequest extends ProgrammeUploadRequest
{
    public function rules(): array
    {
        return [
            'plan_uuid' => ['required', 'uuid'],
            'crew_uuid' => ['sometimes', 'nullable', 'uuid'],
            'role_uuid' => ['required', 'uuid'],
            'item_uuids' => ['required', 'array', 'list', 'min:1', 'max:10000'],
            'item_uuids.*' => ['required', 'uuid', 'distinct:ignore_case'],
        ];
    }
}
