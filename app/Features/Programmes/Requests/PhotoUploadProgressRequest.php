<?php

namespace App\Features\Programmes\Requests;

class PhotoUploadProgressRequest extends ProgrammeUploadRequest
{
    public function rules(): array
    {
        return ['plan_uuid' => ['required', 'uuid']];
    }
}
