<?php

namespace App\Features\Auth\Support;

enum TokenAbility: string
{
    case ProgrammeRead = 'programmes:read';
    case AccountRead = 'account:read';
    case CrewMobile = 'crew-mobile';
    case CustomerMobile = 'customer-mobile';
    case CompetitionObjectsRead = 'competition-objects:read';
    case DownloadLinksManage = 'download-links:manage';
    case ConcertMediaRead = 'concert-media:read';
    case ConcertMediaUpload = 'concert-media:upload';
    case ConcertMediaUpdate = 'concert-media:update';
}
