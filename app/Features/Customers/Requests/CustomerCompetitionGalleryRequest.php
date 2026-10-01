<?php

namespace App\Features\Customers\Requests;

use App\Features\Customers\Services\CustomerCompetitionAccess;
use Illuminate\Foundation\Http\FormRequest;

class CustomerCompetitionGalleryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && app(CustomerCompetitionAccess::class)->allows($this->user(), $this->route('schedulingEvent'));
    }

    public function rules(): array
    {
        return ['folder' => ['nullable', 'uuid'], 'page' => ['nullable', 'integer', 'min:1'],
            'photo' => ['nullable', 'uuid'], 'size' => ['sometimes', 'in:thumbnail,preview,cover']];
    }
}
