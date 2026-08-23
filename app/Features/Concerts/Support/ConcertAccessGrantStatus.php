<?php

namespace App\Features\Concerts\Support;

enum ConcertAccessGrantStatus: string
{
    case Active = 'active';
    case Claimed = 'claimed';
    case Expired = 'expired';
    case Revoked = 'revoked';
}
