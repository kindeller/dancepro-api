<?php

namespace App\Features\Programmes\Requests;

class SyncPhotoTopPicsRequest extends ProgrammeUploadRequest
{
    public function rules(): array
    {
        return [
            'changes' => ['required', 'array', 'list', 'min:1', 'max:100'],
            'changes.*' => ['array:file_uuid,change_uuid,is_top_pic'],
            'changes.*.file_uuid' => ['required', 'uuid', 'distinct:ignore_case'],
            'changes.*.change_uuid' => ['required', 'uuid', 'distinct:ignore_case'],
            'changes.*.is_top_pic' => ['required', 'boolean'],
        ];
    }
}
