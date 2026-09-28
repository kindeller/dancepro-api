<?php

namespace App\Features\Bookings\Requests;

use App\Features\Studios\Requests\UploadStudioEventFilesRequest;

class UploadBookingEventFilesRequest extends UploadStudioEventFilesRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageScheduling') ?? false;
    }

    public function rules(): array
    {
        return parent::rules() + ['replace_uuid' => ['nullable', 'uuid']];
    }
}
