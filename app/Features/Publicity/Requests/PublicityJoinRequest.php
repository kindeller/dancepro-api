<?php

namespace App\Features\Publicity\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PublicityJoinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['version' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]+$/']];
    }
}
