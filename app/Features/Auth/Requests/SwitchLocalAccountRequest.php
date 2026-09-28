<?php

namespace App\Features\Auth\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SwitchLocalAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app()->environment(['local', 'testing']);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer'],
        ];
    }
}
