<?php

namespace App\Features\Programmes\Services;

class ConcertFolderTitle
{
    public function clean(?string $title): string
    {
        $title = str_replace('/', ' ', $title ?? '');
        $title = preg_replace('/[^\p{L}\p{M}\p{N}\s]/u', '', $title) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $title) ?? '');
    }
}
