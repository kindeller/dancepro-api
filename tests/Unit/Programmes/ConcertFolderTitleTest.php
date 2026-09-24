<?php

namespace Tests\Unit\Programmes;

use App\Features\Programmes\Services\ConcertFolderTitle;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ConcertFolderTitleTest extends TestCase
{
    #[DataProvider('titles')]
    public function test_cleans_punctuation_without_losing_letters_numbers_or_accents(?string $input, string $expected): void
    {
        $this->assertSame($expected, (new ConcertFolderTitle)->clean($input));
    }

    public static function titles(): array
    {
        return [
            ['VaVa Voom/That’s What I Like', 'VaVa Voom Thats What I Like'],
            ['Hello;:,.\'"? (World)! & Friends + 2', 'Hello World Friends 2'],
            ['Café / Déjà vu — Beyoncé', 'Café Déjà vu Beyoncé'],
            ["  A//B / C\t D\nE  ", 'A B C D E'],
            ['../A\\B:*?"<>|', 'AB'],
            ['Jazz-Tap_2026', 'JazzTap2026'],
            ['🎵 / ✨', ''],
            [null, ''],
        ];
    }
}
