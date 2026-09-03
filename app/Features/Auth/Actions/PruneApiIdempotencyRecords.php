<?php

namespace App\Features\Auth\Actions;

use App\Features\Auth\Models\ApiIdempotencyRecord;

class PruneApiIdempotencyRecords
{
    public function execute(): int
    {
        return ApiIdempotencyRecord::query()->where('expires_at', '<=', now())->delete();
    }
}
