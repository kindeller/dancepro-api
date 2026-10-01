<?php

namespace App\Features\Concerts\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConcertFamilyActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['player' => ['required', 'uuid'], 'paused' => ['sometimes', 'boolean'], 'position' => ['required', 'integer', 'min:0', 'max:86400'], 'ended' => ['sometimes', 'boolean']];
    }
}
