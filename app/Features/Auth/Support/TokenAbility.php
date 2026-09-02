<?php

namespace App\Features\Auth\Support;

enum TokenAbility: string
{
    case AccountRead = 'account:read';
    case CompetitionObjectsRead = 'competition-objects:read';
    case DownloadLinksManage = 'download-links:manage';
}
