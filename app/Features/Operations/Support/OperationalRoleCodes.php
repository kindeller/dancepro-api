<?php

namespace App\Features\Operations\Support;

final class OperationalRoleCodes
{
    public static function forAssignment(string $roleCode): array
    {
        return match ($roleCode) {
            'competition-photographer-p1', 'competition-photographer-p2' => [$roleCode, 'competition-photographer'],
            default => [$roleCode],
        };
    }

    public static function matches(?string $resourceRoleCode, string $assignmentRoleCode): bool
    {
        return $resourceRoleCode === null
            || in_array($resourceRoleCode, self::forAssignment($assignmentRoleCode), true);
    }
}
