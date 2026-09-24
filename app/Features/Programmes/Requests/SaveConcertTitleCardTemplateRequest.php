<?php

namespace App\Features\Programmes\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveConcertTitleCardTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'logo_source' => ['required', Rule::in(['event', 'studio'])],
            'background_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo_band_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'text_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'separator_start_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'separator_end_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }
}
