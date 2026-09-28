<?php

namespace App\Features\Customers\Models;

use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class CustomerRiskEvent extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'occurred_at' => 'datetime', 'resolved_at' => 'datetime', 'metadata' => 'array'];
    }
}
