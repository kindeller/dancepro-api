<?php

namespace App\Features\Tracking\Requests;

use App\Features\Tracking\Services\ConcertQualityChecklist;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveConcertQualityChecklistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('manageScheduling') ?? false)
            && (! $this->boolean('complete_all') || $this->user()->canAccessAdmin());
    }

    public function rules(): array
    {
        return [
            'complete_all' => ['sometimes', 'boolean'],
            'fingerprint' => ['required', 'string', 'size:64'],
            'checks' => ['sometimes', 'array'],
            'checks.*' => ['string', 'distinct', Rule::in(app(ConcertQualityChecklist::class)->keys())],
        ];
    }
}
