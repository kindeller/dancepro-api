<?php

namespace App\Features\Media\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveMediaPriceListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageScheduling') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'gallery_type' => ['required', Rule::in(['competition', 'concert'])],
            'competition_photo' => ['required_if:gallery_type,competition', 'nullable', 'numeric', 'min:0.01', 'max:100000', 'decimal:0,2'],
            'competition_routine_photos' => ['required_if:gallery_type,competition', 'nullable', 'numeric', 'min:0.01', 'max:100000', 'decimal:0,2'],
            'competition_routine_video' => ['required_if:gallery_type,competition', 'nullable', 'numeric', 'min:0.01', 'max:100000', 'decimal:0,2'],
            'competition_routine_package' => ['required_if:gallery_type,competition', 'nullable', 'numeric', 'min:0.01', 'max:100000', 'decimal:0,2'],
            'concert_photo' => ['required_if:gallery_type,concert', 'nullable', 'numeric', 'min:0.01', 'max:100000', 'decimal:0,2'],
            'gst_rate' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'gst_applicable' => ['nullable', 'array'],
            'gst_applicable.*' => ['string', Rule::in(array_merge(
                ['competition_photo', 'competition_routine_photos', 'competition_routine_video', 'competition_routine_package'],
                ['concert_photo'],
            ))],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
