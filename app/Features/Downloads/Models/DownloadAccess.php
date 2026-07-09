<?php

namespace App\Features\Downloads\Models;

use Database\Factories\DownloadAccessFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'download_link_id',
    'accessed_at',
    'ip_address',
    'user_agent',
    'referrer',
    'was_successful',
    'failure_reason',
])]
class DownloadAccess extends Model
{
    /** @use HasFactory<DownloadAccessFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<DownloadLink, $this>
     */
    public function downloadLink(): BelongsTo
    {
        return $this->belongsTo(DownloadLink::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accessed_at' => 'datetime',
            'was_successful' => 'boolean',
        ];
    }

    protected static function newFactory(): DownloadAccessFactory
    {
        return DownloadAccessFactory::new();
    }
}
