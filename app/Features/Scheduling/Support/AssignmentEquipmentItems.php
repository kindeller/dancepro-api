<?php

namespace App\Features\Scheduling\Support;

use Illuminate\Support\Collection;

class AssignmentEquipmentItems
{
    public const MEDIA_CODES = ['photos', 'videos'];

    public static function all(): array
    {
        return [
            'video_1' => ['🔵', 'Video 1'], 'video_2' => ['🟢', 'Video 2'], 'video_3' => ['🔴', 'Video 3'],
            'backdrop_1' => ['🟠', 'Backdrop 1'], 'backdrop_2' => ['🟡', 'Backdrop 2'],
            'photos' => ['💾', 'Photos'], 'videos' => ['💾', 'Videos'],
        ];
    }

    public static function mediaSummary(Collection $responsibilities): ?array
    {
        $media = $responsibilities->whereIn('item_code', self::MEDIA_CODES)->sortBy(fn ($item) => array_search($item->item_code, self::MEDIA_CODES));
        if ($media->isEmpty()) {
            return null;
        }
        $brings = $media->where('is_bringing', true)->pluck('item_code')->join(' & ');
        $takes = $media->where('is_taking', true)->pluck('item_code')->join(' & ');
        $notes = $media->filter(fn ($item) => filled($item->other_notes))
            ->map(fn ($item) => ucfirst($item->item_code).': '.$item->other_notes)->join(' · ');

        return [
            'bringing' => $brings !== '',
            'taking' => $takes !== '',
            'notes' => $notes,
            'description' => collect([$brings ? 'Bring '.$brings : null, $takes ? 'Take '.$takes : null, $notes])->filter()->join(' · '),
        ];
    }
}
