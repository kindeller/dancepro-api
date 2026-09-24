<?php

namespace App\Features\Programmes\Requests;

class UploadProgrammePhotoRequest extends ProgrammeUploadRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['content_type' => $this->header('Content-Type')]);
    }

    public function rules(): array
    {
        return ['content_type' => ['required', 'in:image/jpeg']];
    }
}
