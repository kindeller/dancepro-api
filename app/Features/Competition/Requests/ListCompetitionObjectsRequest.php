<?php

namespace App\Features\Competition\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class ListCompetitionObjectsRequest extends FormRequest
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
            'prefix' => ['nullable', 'string', 'max:1000'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'continuation_token' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function passedValidation(): void
    {
        $prefix = $this->normalizePrefix($this->string('prefix')->toString());

        if ($prefix === '') {
            return;
        }

        if (str_starts_with($prefix, '/')) {
            $this->fail('Storage prefixes must be relative paths.');
        }

        if (preg_match('/(^|\/)\.\.(\/|$)/', $prefix) === 1) {
            $this->fail('Storage prefixes cannot contain path traversal.');
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $prefix) === 1) {
            $this->fail('Storage prefixes cannot contain control characters.');
        }

        $this->merge(['prefix' => trim($prefix, '/')]);
    }

    private function normalizePrefix(string $prefix): string
    {
        $prefix = trim(str_replace('\\', '/', $prefix));

        while (str_contains($prefix, '//')) {
            $prefix = str_replace('//', '/', $prefix);
        }

        return $prefix;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages([
            'prefix' => [$message],
        ]);
    }
}
