<?php

namespace App\Features\Programmes\Services;

class ConcertFolderTitle
{
    public function clean(?string $title, bool $stripAccents = true): string
    {
        $title ??= '';
        if ($stripAccents) {
            $title = \Normalizer::normalize($title, \Normalizer::FORM_D) ?: $title;
            $title = preg_replace('/\p{M}+/u', '', $title) ?? '';
            $title = \Normalizer::normalize($title, \Normalizer::FORM_C) ?: $title;
        }
        $title = str_replace('/', ' ', $title);
        $title = preg_replace('/[^\p{L}\p{M}\p{N}\s]/u', '', $title) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $title) ?? '');
    }
}
