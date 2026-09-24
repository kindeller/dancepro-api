<?php

namespace App\Features\Programmes\Requests;

class AssignCompetitionVideoRequest extends AdminCompetitionVideoRequest
{
    public function rules(): array
    {
        return ['item_uuid' => ['required', 'uuid']];
    }
}
