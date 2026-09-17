<?php

namespace App\Features\Admin\Requests;

use App\Features\Studios\Models\Studio;
use App\Features\Studios\Support\StudioStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveStudioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageStudios') ?? false;
    }

    public function rules(): array
    {
        /** @var Studio|null $studio */
        $studio = $this->route('studio');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('studios', 'slug')->ignore($studio)],
            'status' => ['required', Rule::enum(StudioStatus::class)],
            'description' => ['nullable', 'string', 'max:5000'],
            'cover_image_url' => ['nullable', 'url:http,https', 'max:2048'],
            'brand_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
