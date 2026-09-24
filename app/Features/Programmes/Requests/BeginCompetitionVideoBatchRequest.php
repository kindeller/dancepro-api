<?php

namespace App\Features\Programmes\Requests;

class BeginCompetitionVideoBatchRequest extends CompetitionVideoBatchRequest
{
    public function rules(): array
    {
        return [
            'batch_uuid' => ['required', 'uuid'],
            'plan_uuid' => ['required', 'uuid'],
            'role_uuid' => ['required', 'uuid'],
        ];
    }
}
