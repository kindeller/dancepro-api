<?php

namespace App\Features\Concerts\Support;

enum ConcertAccessMethod: string
{
    case Password = 'password';
    case Account = 'account';
    case SavedAccess = 'saved_access';
    case Staff = 'staff';
}
