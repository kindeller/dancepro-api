<?php

namespace App\Features\Programmes\Requests;

class ReservePhotoFilesRequest extends RegisterPhotoContributionsRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['item_uuids'], $rules['item_uuids.*']);

        return $rules + [
            'photos' => ['required', 'array', 'list', 'min:1', 'max:500'],
            'photos.*' => ['array:photo_uuid,item_uuid,file_name,byte_count,sha256,is_top_pic'],
            'photos.*.photo_uuid' => ['required', 'uuid', 'distinct:ignore_case'],
            'photos.*.item_uuid' => ['required', 'uuid'],
            'photos.*.file_name' => ['required', 'string', 'max:255', 'regex:~^[^/\\\\\x00-\x1f\x7f]+\.(?:jpg|jpeg)$~i'],
            'photos.*.byte_count' => ['required', 'integer', 'min:4', 'max:536870912'],
            'photos.*.sha256' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'photos.*.is_top_pic' => ['required', 'boolean'],
        ];
    }
}
