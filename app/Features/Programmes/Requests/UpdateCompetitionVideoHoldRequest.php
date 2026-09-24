<?php

namespace App\Features\Programmes\Requests;

class UpdateCompetitionVideoHoldRequest extends AdminCompetitionVideoRequest
{
    public function rules(): array
    {
        return ['item_uuid' => ['required', 'uuid'], 'is_held' => ['required', 'boolean']];
    }
}
