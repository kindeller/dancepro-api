<?php

namespace App\Features\Concerts\Models;

use App\Features\Concerts\Support\ConcertStatus;
use App\Features\Downloads\Models\DownloadLink;
use App\Features\Media\Models\MediaCollection;
use App\Features\Studios\Models\Studio;
use App\Models\User;
use App\Shared\Models\HasPublicUuid;
use Database\Factories\ConcertFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Hash;

#[Fillable(['uuid', 'studio_id', 'name', 'slug', 'status', 'event_date', 'event_end_date', 'venue_name', 'description', 'cover_image_url', 'brand_color', 'is_enabled', 'requires_approval', 'approved_at', 'approved_by_user_id', 'available_from', 'available_until', 'program_url', 'external_gallery_url', 'storage_disk', 'storage_prefix', 'access_password_hash', 'published_at', 'archived_at', 'created_by_user_id', 'updated_by_user_id', 'legacy_id', 'notes'])]
class Concert extends Model
{
    /** @use HasFactory<ConcertFactory> */
    use HasFactory, HasPublicUuid, SoftDeletes;

    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }

    public function mediaCollections(): HasMany
    {
        return $this->hasMany(MediaCollection::class);
    }

    public function accessGrants(): HasMany
    {
        return $this->hasMany(ConcertAccessGrant::class);
    }

    public function accesses(): HasMany
    {
        return $this->hasMany(ConcertAccess::class);
    }

    public function downloadLinks(): HasMany
    {
        return $this->hasMany(DownloadLink::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function isPubliclyAvailable(): bool
    {
        return $this->is_enabled
            && $this->status === ConcertStatus::Published
            && (! $this->requires_approval || $this->approved_at !== null)
            && ($this->available_from === null || $this->available_from->isPast())
            && ($this->available_until === null || $this->available_until->isFuture());
    }

    public function requiresPassword(): bool
    {
        return $this->access_password_hash !== null;
    }

    public function passwordMatches(string $password): bool
    {
        return $this->access_password_hash !== null && Hash::check($password, $this->access_password_hash);
    }

    protected function accessPasswordHash(): Attribute
    {
        return Attribute::set(fn (?string $value) => $value === null || Hash::needsRehash($value) ? ($value === null ? null : Hash::make($value)) : $value);
    }

    protected function casts(): array
    {
        return [
            'status' => ConcertStatus::class,
            'event_date' => 'date', 'event_end_date' => 'date',
            'published_at' => 'datetime', 'archived_at' => 'datetime',
            'is_enabled' => 'boolean', 'requires_approval' => 'boolean', 'approved_at' => 'datetime',
            'available_from' => 'datetime', 'available_until' => 'datetime',
        ];
    }

    protected static function newFactory(): ConcertFactory
    {
        return ConcertFactory::new();
    }
}
