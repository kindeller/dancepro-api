<?php

namespace App\Features\Publicity\Requests;

use App\Features\Media\Requests\AdminTopPicsRequest;

class SlideshowSettingsRequest extends AdminTopPicsRequest
{
    public function rules(): array
    {
        return ['enabled' => ['required', 'boolean'], 'photo_seconds' => ['required', 'integer', 'between:2,60'], 'promotion_every' => ['required', 'integer', 'between:1,50']];
    }
}
