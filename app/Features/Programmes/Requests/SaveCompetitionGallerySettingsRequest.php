<?php

namespace App\Features\Programmes\Requests;

use Illuminate\Validation\Rule;

class SaveCompetitionGallerySettingsRequest extends ProgrammeVersionRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('display_title'))) {
            $this->merge(['display_title' => trim($this->input('display_title')) ?: null]);
        }
    }

    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:0'],
            'action' => ['required', Rule::in(['title', 'cover', 'reset_cover', 'login_code', 'watermark'])],
            'display_title' => ['exclude_unless:action,title', 'nullable', 'string', 'max:255'],
            'login_code' => ['exclude_unless:action,login_code', 'required', 'string', 'regex:/\A[A-Za-z0-9-]{6,64}\z/'],
            'photo_uuid' => ['exclude_unless:action,cover', 'required', 'uuid'],
            'watermark_uuid' => ['exclude_unless:action,watermark', 'nullable', 'uuid'],
        ];
    }
}
