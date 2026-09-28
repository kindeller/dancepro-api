<?php

namespace App\Features\Publicity\Requests;

use App\Features\Media\Requests\AdminTopPicsRequest;
use Illuminate\Validation\Rule;

class SavePublicitySlideRequest extends AdminTopPicsRequest
{
    public function rules(): array
    {
        return [
            'audience' => [Rule::requiredIf(! $this->route('schedulingEvent') && ! $this->route('slide')), 'in:competition,concert'],
            'title' => ['required', 'string', 'max:160'],
            'body' => ['nullable', 'string', 'max:1200'],
            'video' => ['nullable', 'file', 'mimes:mp4', 'max:102400', 'prohibits:image'],
            'play_sound' => ['sometimes', 'boolean'],
            'image' => ['prohibits:video', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240', 'dimensions:max_width=6000,max_height=6000'],
            'enabled' => ['required', 'boolean'],
            'duration_seconds' => ['sometimes', 'integer', 'between:3,120'],
            'sort_order' => ['required', 'integer', 'between:0,9999'],
        ];
    }
}
