<?php

namespace App\Features\Concerts\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConcertFamilyPlaybackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['player' => ['required', 'uuid']];
    }
}
