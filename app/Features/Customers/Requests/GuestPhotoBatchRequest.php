<?php

namespace App\Features\Customers\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GuestPhotoBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'in:add,favourite'],
            'item_uuid' => ['required', 'uuid'],
            'photo_uuids' => ['required', 'array', 'min:1', 'max:100'],
            'photo_uuids.*' => ['required', 'uuid', 'distinct'],
        ];
    }
}
