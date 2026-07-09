<?php

namespace App\Features\Admin\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAdminDownloadLinksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'keys' => ['required', 'string'],
            'disk' => ['nullable', 'string'],
            'days' => ['nullable', 'integer', 'min:1', 'max:60'],
            'purpose' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->storageKeys() === []) {
                    $validator->errors()->add('keys', 'Enter at least one storage key.');
                }
            },
        ];
    }

    /**
     * @return array<int, string>
     */
    public function storageKeys(): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $this->string('keys')->toString()) ?: [])
            ->map(fn (string $key): string => trim($key))
            ->filter()
            ->values()
            ->all();
    }
}
