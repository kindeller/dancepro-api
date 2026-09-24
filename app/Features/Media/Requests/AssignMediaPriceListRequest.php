<?php

namespace App\Features\Media\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignMediaPriceListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageScheduling') ?? false;
    }

    public function rules(): array
    {
        return [
            'target' => ['required', 'regex:/\A(competition|concert|collection|routine):[1-9][0-9]*\z/'],
            'media_price_list_id' => ['nullable', 'integer', 'exists:media_price_lists,id'],
        ];
    }
}
