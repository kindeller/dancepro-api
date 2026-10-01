<?php

namespace App\Features\Concerts\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyConcertFamilyShareRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['code' => ['required', 'digits:6']];
    }
}
