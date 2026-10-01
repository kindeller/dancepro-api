<?php

namespace App\Features\Customers\Requests;

use App\Features\Customers\Services\CustomerConcertGallery;
use Illuminate\Foundation\Http\FormRequest;

class CustomerConcertGalleryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && app(CustomerConcertGallery::class)->allows($this->user(), $this->route('concert'));
    }

    public function rules(): array
    {
        return ['group' => ['nullable', 'uuid'], 'folder' => ['nullable', 'uuid'], 'page' => ['nullable', 'integer', 'min:1'],
            'size' => ['sometimes', 'in:thumbnail,preview,cover']];
    }
}
