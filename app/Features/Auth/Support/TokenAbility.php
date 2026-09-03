<?php

namespace App\Features\Auth\Support;

enum TokenAbility: string
{
    case AccountRead = 'account:read';
    case CrewMobile = 'crew-mobile';
    case CompetitionObjectsRead = 'competition-objects:read';
    case DownloadLinksManage = 'download-links:manage';
}
