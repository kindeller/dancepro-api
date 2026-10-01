<?php

namespace App\Features\Website\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveWebsitePageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessAdmin() ?? false;
    }

    public function rules(): array
    {
        $link = function ($attribute, $value, $fail) {
            if (! $value) {
                return;
            }
            if (str_starts_with($value, '/') && ! str_starts_with($value, '//') && ! str_contains($value, '\\') && ! preg_match('/[\x00-\x20]/', $value)) {
                return;
            }
            if (filter_var($value, FILTER_VALIDATE_URL) && parse_url($value, PHP_URL_SCHEME) === 'https') {
                return;
            }
            $fail('Use a local page path beginning with / or a full https:// address.');
        };

        return ['action' => ['required', 'in:draft,publish,hide'], 'eyebrow' => ['required', 'string', 'max:100'], 'heading' => ['required', 'string', 'max:180'], 'description' => ['required', 'string', 'max:500'], 'body' => ['nullable', 'string', 'max:12000'], 'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'], 'remove_image' => ['sometimes', 'boolean'], 'sections' => ['sometimes', 'array', 'max:20'], 'sections.*.title' => ['required', 'string', 'max:180'], 'sections.*.body' => ['nullable', 'string', 'max:6000'], 'sections.*.link_label' => ['nullable', 'string', 'max:100'], 'sections.*.link_url' => ['nullable', 'string', 'max:1000', $link], 'sections.*.kind' => ['sometimes', 'in:content,crew_placeholder'], 'sections.*.visible' => ['required', 'boolean']];
    }
}
