<?php

namespace App\Features\Customers\Models;

use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class CustomerPrivacyRequest extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['requested_at' => 'datetime', 'completed_at' => 'datetime', 'metadata' => 'array'];
    }
}
