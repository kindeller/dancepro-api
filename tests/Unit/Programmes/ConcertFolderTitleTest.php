<?php

namespace Tests\Unit\Programmes;

use App\Features\Programmes\Services\ConcertFolderTitle;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ConcertFolderTitleTest extends TestCase
{
    #[DataProvider('titles')]
    public function test_cleans_punctuation_and_accents_for_folder_names(?string $input, string $expected): void
    {
        $this->assertSame($expected, (new ConcertFolderTitle)->clean($input));
    }

    public function test_can_validate_legacy_accented_folder_titles(): void
    {
        $this->assertSame('Café', (new ConcertFolderTitle)->clean('Café!', stripAccents: false));
    }

    public static function titles(): array
    {
        return [
            ['VaVa Voom/That’s What I Like', 'VaVa Voom Thats What I Like'],
            ['Hello;:,.\'"? (World)! & Friends + 2', 'Hello World Friends 2'],
            ['Café / Déjà vu — Beyoncé', 'Cafe Deja vu Beyonce'],
            ["  A//B / C\t D\nE  ", 'A B C D E'],
            ['../A\\B:*?"<>|', 'AB'],
            ['Jazz-Tap_2026', 'JazzTap2026'],
            ['🎵 / ✨', ''],
            ['BREAKÍN DISHES', 'BREAKIN DISHES'],
            ["BREAKI\u{0301}N DISHES", 'BREAKIN DISHES'],
            [null, ''],
        ];
    }
}
