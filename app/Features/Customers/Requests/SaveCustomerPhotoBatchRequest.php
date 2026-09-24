<?php

namespace App\Features\Customers\Requests;

use App\Features\Customers\Services\CustomerCompetitionAccess;
use Illuminate\Foundation\Http\FormRequest;

class SaveCustomerPhotoBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && app(CustomerCompetitionAccess::class)->allows($this->user(), $this->route('schedulingEvent'));
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
