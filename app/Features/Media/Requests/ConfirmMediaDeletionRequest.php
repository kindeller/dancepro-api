<?php

namespace App\Features\Media\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmMediaDeletionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'confirmation' => ['required', 'in:DELETE'],
            'digest' => ['required', 'string', 'size:64'],
        ];
    }
}
