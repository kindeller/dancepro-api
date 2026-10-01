<?php

namespace App\Features\Concerts\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConcertFamilyPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['family_sharing_enabled' => ['required', 'boolean'], 'family_downloads_enabled' => ['required', 'boolean']];
    }
}
