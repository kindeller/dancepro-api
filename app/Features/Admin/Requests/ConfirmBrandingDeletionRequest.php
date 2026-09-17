<?php

namespace App\Features\Admin\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmBrandingDeletionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can($this->routeIs('admin.branding.studios.*') ? 'manageStudios' : 'manageConcerts') ?? false;
    }

    public function rules(): array
    {
        return ['confirmation' => ['required', 'in:DELETE'], 'digest' => ['required', 'string', 'size:64']];
    }
}
