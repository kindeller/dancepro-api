<?php

namespace App\Features\Programmes\Requests;

class StartProgrammeRequest extends ProgrammeVersionRequest
{
    public function rules(): array
    {
        return ['programme' => ['nullable', 'file', 'max:20480', 'mimes:pdf', 'mimetypes:application/pdf']];
    }
}
