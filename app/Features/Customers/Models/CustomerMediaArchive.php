<?php

namespace App\Features\Customers\Models;

use App\Models\User;
use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerMediaArchive extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'asset_count' => 'integer', 'source_bytes' => 'integer', 'archive_bytes' => 'integer',
            'download_count' => 'integer', 'last_download_at' => 'datetime', 'expires_at' => 'datetime',
        ];
    }
}
