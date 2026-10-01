<?php

namespace App\Shared\Support;

class AccountName
{
    public static function split(string $name): array
    {
        $parts = preg_split('/\s+/u', trim($name), 2) ?: [];

        return ['first_name' => $parts[0] ?? '', 'last_name' => $parts[1] ?? null];
    }

    public static function input(array $input): array
    {
        if (array_key_exists('first_name', $input) || array_key_exists('last_name', $input)) {
            if (! is_string($input['first_name'] ?? null) || ! is_string($input['last_name'] ?? '')) {
                return $input;
            }
            $input['first_name'] = trim($input['first_name']);
            $input['last_name'] = trim($input['last_name'] ?? '') ?: null;
            $input['name'] = trim($input['first_name'].' '.($input['last_name'] ?? ''));
        } elseif (is_string($input['name'] ?? null)) {
            $input = array_merge($input, self::split($input['name']));
        }

        return $input;
    }
}
