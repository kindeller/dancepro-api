<?php

namespace App\Features\Customers\Requests;

use App\Features\Customers\Services\GuestGalleryAccessManager;
use Illuminate\Foundation\Http\FormRequest;

class GuestCompetitionGalleryRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('discount_code')) {
            $this->merge(['discount_code' => strtoupper(trim((string) $this->input('discount_code')))]);
        }
    }

    public function authorize(): bool
    {
        app(GuestGalleryAccessManager::class)->competition($this, $this->route('schedulingEvent'));

        return true;
    }

    public function rules(): array
    {
        return ['folder' => ['nullable', 'uuid'], 'page' => ['nullable', 'integer', 'min:1'],
            'photo' => ['nullable', 'uuid'], 'size' => ['sometimes', 'in:thumbnail,preview,cover'],
            'discount_code' => ['nullable', 'regex:/\A[A-Z0-9-]{4,32}\z/']];
    }
}
