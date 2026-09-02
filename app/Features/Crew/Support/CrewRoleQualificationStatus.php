<?php

namespace App\Features\Crew\Support;

enum CrewRoleQualificationStatus: string
{
    case Approved = 'approved';
    case Training = 'training';
    case Inactive = 'inactive';
}
