<?php

namespace App\Features\Customers\Requests;

use App\Features\Customers\Services\CustomerConcertGallery;
use Illuminate\Foundation\Http\FormRequest;

class SaveCustomerConcertPhotoSelectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && app(CustomerConcertGallery::class)->allows($this->user(), $this->route('concert'));
    }

    public function rules(): array
    {
        return ['asset_uuid' => ['required', 'uuid'], 'action' => ['required', 'in:favourite,unfavourite,add,remove']];
    }
}
