<?php

namespace App\Features\Programmes\Requests;

class FinishPhotoUploadsRequest extends ProgrammeUploadRequest
{
    public function rules(): array
    {
        return [
            'plan_uuid' => ['required', 'uuid'],
            'crew_uuid' => ['sometimes', 'nullable', 'uuid'],
            'role_uuid' => ['required', 'uuid'],
        ];
    }
}
