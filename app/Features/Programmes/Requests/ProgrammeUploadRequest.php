<?php

namespace App\Features\Programmes\Requests;

use App\Features\Programmes\Policies\ProgrammePolicy;

class ProgrammeUploadRequest extends ProgrammeVersionRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && app(ProgrammePolicy::class)->upload($this->user(), $this->route('schedulingEvent'));
    }
}
