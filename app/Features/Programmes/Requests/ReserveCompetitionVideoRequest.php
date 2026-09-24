<?php

namespace App\Features\Programmes\Requests;

class ReserveCompetitionVideoRequest extends CompetitionVideoBatchRequest
{
    public function rules(): array
    {
        return [
            'file_uuid' => ['required', 'uuid'],
            'file_name' => ['required', 'string', 'max:255', 'regex:~^[^/\\\\\x00-\x1f\x7f]+\.(?:mp4|mov|m4v|mxf)$~i'],
            'sha256' => ['required', 'regex:/^[a-f0-9]{64}$/'],
            'byte_count' => ['required', 'integer', 'min:1', 'max:5368709120'],
        ];
    }
}
