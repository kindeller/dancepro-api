<?php

namespace App\Features\Customers\Requests;

use App\Features\Customers\Services\GuestGalleryAccessManager;
use Illuminate\Foundation\Http\FormRequest;

class GuestConcertGalleryRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('discount_code')) {
            $this->merge(['discount_code' => strtoupper(trim((string) $this->input('discount_code')))]);
        }
    }

    public function authorize(): bool
    {
        app(GuestGalleryAccessManager::class)->concert($this, $this->route('concert'));

        return true;
    }

    public function rules(): array
    {
        return ['group' => ['nullable', 'uuid'], 'folder' => ['nullable', 'uuid'], 'page' => ['nullable', 'integer', 'min:1'],
            'size' => ['sometimes', 'in:thumbnail,preview'],
            'discount_code' => ['nullable', 'regex:/\A[A-Z0-9-]{4,32}\z/']];
    }
}
