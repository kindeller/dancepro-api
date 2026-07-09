<?php

namespace App\Features\Downloads\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateDownloadLinksRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'keys' => ['required', 'array', 'max:200'],
            'keys.*' => ['required', 'string'],
            'disk' => ['nullable', 'string'],
            'days' => ['nullable', 'integer', 'min:1', 'max:60'],
            'purpose' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
