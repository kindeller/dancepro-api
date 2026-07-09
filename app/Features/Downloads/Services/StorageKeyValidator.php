<?php

namespace App\Features\Downloads\Services;

use Illuminate\Validation\ValidationException;

class StorageKeyValidator
{
    public function normalize(string $key): string
    {
        $key = trim(str_replace('\\', '/', $key));

        while (str_contains($key, '//')) {
            $key = str_replace('//', '/', $key);
        }

        return $key;
    }

    public function validate(string $key): string
    {
        $key = $this->normalize($key);

        if ($key === '') {
            $this->fail('Storage keys cannot be empty.');
        }

        if (str_starts_with($key, '/')) {
            $this->fail('Storage keys must be relative paths.');
        }

        if (preg_match('/(^|\/)\.\.(\/|$)/', $key) === 1) {
            $this->fail('Storage keys cannot contain path traversal.');
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $key) === 1) {
            $this->fail('Storage keys cannot contain control characters.');
        }

        return $key;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages([
            'keys' => [$message],
        ]);
    }
}
