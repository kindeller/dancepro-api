<?php

namespace App\Features\Programmes\Requests;

class UploadProgrammeSourceRequest extends ProgrammeVersionRequest
{
    public function rules(): array
    {
        return parent::rules() + ['programme' => ['required', 'file', 'max:20480', 'mimes:pdf', 'mimetypes:application/pdf']];
    }
}
