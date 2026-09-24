<?php

namespace App\Features\Programmes\Requests;

class AdminPhotoUploadsRequest extends ProgrammeVersionRequest
{
    public function rules(): array
    {
        return ['plan_uuid' => ['nullable', 'uuid']];
    }
}
