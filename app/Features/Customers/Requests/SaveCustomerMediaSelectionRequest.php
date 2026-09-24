<?php

namespace App\Features\Customers\Requests;

use App\Features\Customers\Services\CustomerCompetitionAccess;
use Illuminate\Foundation\Http\FormRequest;

class SaveCustomerMediaSelectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && app(CustomerCompetitionAccess::class)->allows($this->user(), $this->route('schedulingEvent'));
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'in:favourite,unfavourite,add,remove'],
            'product' => ['required', 'in:photo,routine_photos,routine_video,routine_package'],
            'item_uuid' => ['required', 'uuid'],
            'photo_uuid' => ['nullable', 'required_if:product,photo', 'uuid'],
        ];
    }
}
