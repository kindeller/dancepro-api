<?php

namespace App\Features\Customers\Support;

class CustomerDancerNameMatcher
{
    public function canonical(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = preg_replace('/[^\pL\pN]+/u', ' ', $name) ?? $name;

        return trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
    }

    public function startsWith(string $title, string $name): bool
    {
        $parts = preg_split('/[\s\p{Pd}-]+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY);
        if ($parts === false || $parts === []) {
            return false;
        }

        $phrase = implode('[\s\p{Pd}-]+', array_map(fn (string $part): string => preg_quote($part, '/'), $parts));

        return preg_match('/^'.$phrase.'(?![\pL\pN])/iu', trim($title)) === 1;
    }

    public function matches(string $title, string $name): bool
    {
        $parts = preg_split('/[\s\p{Pd}-]+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY);
        if ($parts === false || $parts === []) {
            return false;
        }

        $phrase = implode('[\s\p{Pd}-]+', array_map(fn (string $part): string => preg_quote($part, '/'), $parts));

        return preg_match('/(?<![\pL\pN])'.$phrase.'(?![\pL\pN])/iu', $title) === 1;
    }
}
