<?php

namespace App\Features\Publicity\Requests;

class SelectScreenEventRequest extends ScreenRequest
{
    public function rules(): array
    {
        return ['event_uuid' => ['required', 'uuid']];
    }
}
