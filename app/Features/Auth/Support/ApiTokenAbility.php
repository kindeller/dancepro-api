<?php

namespace App\Features\Auth\Support;

enum ApiTokenAbility: string
{
    case ConcertMediaRead = 'concert-media:read';
    case ConcertMediaUpload = 'concert-media:upload';
    case ConcertMediaUpdate = 'concert-media:update';
    case CompetitionObjectsRead = 'competition-objects:read';
    case DownloadLinksManage = 'download-links:manage';

    /** @return list<string> */
    public static function legacyAbilities(): array
    {
        return [self::CompetitionObjectsRead->value, self::DownloadLinksManage->value];
    }

    /**
     * @return list<string>
     */
    public static function staffAbilities(): array
    {
        return array_map(
            static fn (self $ability): string => $ability->value,
            self::cases(),
        );
    }
}
