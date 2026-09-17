<?php

namespace App\Features\Concerts\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UnlockConcertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }
}
