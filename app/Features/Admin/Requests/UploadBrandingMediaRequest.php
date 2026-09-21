<?php

namespace App\Features\Admin\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class UploadBrandingMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can($this->routeIs('admin.branding.studios.*') ? 'manageStudios' : 'manageConcerts') ?? false;
    }

    public function rules(): array
    {
        $owner = $this->route('studio') ?? $this->route('concert');
        $kind = $this->routeIs('admin.branding.concerts.program.store') ? 'program' : 'cover';
        $keyField = $kind === 'program' ? 'program_storage_key' : 'cover_image_storage_key';

        $rules = [
            'file' => ['required', File::types($this->routeIs('admin.branding.concerts.program.store') ? ['pdf'] : ['jpg', 'jpeg', 'png', 'webp'])->max(10 * 1024)],
        ];

        if (filled($owner?->{$keyField})) {
            $rules['replace_confirm'] = ['required', 'accepted'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return ['replace_confirm.accepted' => 'Tick Confirm replacement before replacing the current file.',
            'replace_confirm.required' => 'Tick Confirm replacement before replacing the current file.'];
    }
}
