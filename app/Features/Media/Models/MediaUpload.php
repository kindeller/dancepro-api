<?php

namespace App\Features\Media\Models;

use App\Features\Media\Support\MediaUploadKind;
use App\Features\Media\Support\MediaUploadStatus;
use App\Models\User;
use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['uuid', 'media_asset_id', 'user_id', 'kind', 'status', 'idempotency_key', 'relative_path', 'storage_disk', 'storage_key', 'provider_upload_id', 'content_type', 'size_bytes', 'checksum_algorithm', 'checksum', 'files', 'expires_at', 'completed_at', 'aborted_at', 'metadata'])]
class MediaUpload extends Model
{
    use HasPublicUuid;

    public function asset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'kind' => MediaUploadKind::class,
            'status' => MediaUploadStatus::class,
            'size_bytes' => 'integer',
            'files' => 'array',
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
            'aborted_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
