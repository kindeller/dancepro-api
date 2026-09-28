<?php

namespace App\Features\Publicity\Requests;

use App\Features\Media\Requests\AdminTopPicsRequest;

class SlideshowIndexRequest extends AdminTopPicsRequest
{
    public function rules(): array
    {
        return parent::rules() + ['advertising_type' => ['sometimes', 'in:competition,concert']];
    }
}
