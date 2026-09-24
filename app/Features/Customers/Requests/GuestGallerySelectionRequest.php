<?php

namespace App\Features\Customers\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuestGallerySelectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['add', 'remove', 'favourite', 'unfavourite'])],
            'product' => ['nullable', Rule::in(['photo', 'routine_photos', 'routine_video', 'routine_package'])],
            'item_uuid' => ['nullable', 'uuid'], 'photo_uuid' => ['nullable', 'uuid'], 'asset_uuid' => ['nullable', 'uuid'],
        ];
    }
}
