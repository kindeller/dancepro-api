<?php

namespace App\Features\Auth\Support;

use App\Models\User;
use Carbon\CarbonInterface;

final readonly class CrewMobileLoginResult
{
    public function __construct(
        public CrewMobileLoginStatus $status,
        public ?User $user = null,
        public ?string $plainTextToken = null,
        public ?CarbonInterface $expiresAt = null,
    ) {}
}
