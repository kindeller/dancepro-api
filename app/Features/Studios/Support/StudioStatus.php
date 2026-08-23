<?php

namespace App\Features\Studios\Support;

enum StudioStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Archived = 'archived';
}
