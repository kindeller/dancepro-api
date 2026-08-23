<?php

namespace App\Features\Concerts\Support;

enum ConcertAccessGrantSource: string
{
    case Password = 'password';
    case Invitation = 'invitation';
    case StaffAssignment = 'staff_assignment';
    case Order = 'order';
    case LegacyImport = 'legacy_import';
}
