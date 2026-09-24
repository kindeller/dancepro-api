<?php

namespace App\Features\Programmes\Requests;

class CompetitionFolderInventoryRequest extends SyncProgrammeRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'role_uuid' => ['required', 'uuid'], 'device_uuid' => ['required', 'uuid'],
            'folders' => ['present', 'array', 'list', 'max:10000'],
            'folders.*' => ['array:node_uuid,title'],
            'folders.*.node_uuid' => ['required', 'uuid', 'distinct:ignore_case'],
            'folders.*.title' => ['required', 'string', 'max:500'],
        ];
    }
}
