<?php

namespace App\Features\Customers\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GrantComplimentaryMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'customer_lookup' => ['required', 'string', 'max:255'],
            'source_kind' => ['required', Rule::in(['competition_photo', 'competition_item', 'competition_video', 'concert_photo', 'concert_item'])],
            'event_uuid' => ['nullable', 'uuid'],
            'source_uuid' => ['required', 'uuid'],
            'include_video' => ['sometimes', 'boolean'],
            'notify_customer' => ['sometimes', 'boolean'],
            'note' => ['nullable', 'string', 'max:500'],
            'return_to' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
