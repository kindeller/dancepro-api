<?php

namespace App\Features\Programmes\Requests;

class PublishConcertTitleCardsRequest extends SaveConcertTitleCardTemplateRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'revision_uuid' => ['required', 'uuid'],
            'content_fingerprint' => ['required', 'string', 'size:64'],
            'package' => ['required', 'file', 'mimes:zip', 'max:204800'],
        ];
    }
}
