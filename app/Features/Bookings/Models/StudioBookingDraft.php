<?php

namespace App\Features\Bookings\Models;

use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class StudioBookingDraft extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['data' => 'array', 'version' => 'integer'];
    }
}
