<?php

namespace App\Features\Programmes\Requests;

class CompetitionFolderProgressRequest extends ProgrammeUploadRequest
{
    public function rules(): array
    {
        return ['revision_uuid' => ['required', 'uuid']];
    }
}
