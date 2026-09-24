<?php

namespace App\Features\Media\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateConcertPhotoCoverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessAdmin() ?? false;
    }

    public function rules(): array
    {
        return ['asset_uuid' => ['nullable', 'uuid']];
    }
}
