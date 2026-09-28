<?php

namespace App\Features\Programmes\Models;

use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class ConcertTitleCardPackage extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['settings' => 'array', 'size_bytes' => 'integer', 'item_count' => 'integer'];
    }
}
