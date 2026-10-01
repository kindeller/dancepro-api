<?php

namespace App\Features\Programmes\Requests;

class CompetitionGalleryRequest extends ProgrammeVersionRequest
{
    public function rules(): array
    {
        return ['folder' => ['nullable', 'uuid'], 'page' => ['nullable', 'integer', 'min:1'], 'size' => ['sometimes', 'in:thumbnail,preview,cover']];
    }
}
