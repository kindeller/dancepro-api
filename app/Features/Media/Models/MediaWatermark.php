<?php

namespace App\Features\Media\Models;

use App\Features\Programmes\Models\CompetitionGallerySetting;
use App\Models\User;
use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MediaWatermark extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'is_active' => 'boolean'];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function competitionGallerySettings(): HasMany
    {
        return $this->hasMany(CompetitionGallerySetting::class, 'media_watermark_id');
    }

    public function collections(): HasMany
    {
        return $this->hasMany(MediaCollection::class, 'media_watermark_id');
    }

    public function concertReleaseGroups(): HasMany
    {
        return $this->hasMany(ConcertPhotoReleaseGroup::class, 'media_watermark_id');
    }

    public function assignmentCount(): int
    {
        return (int) ($this->competition_gallery_settings_count ?? $this->competitionGallerySettings()->count())
            + (int) ($this->collections_count ?? $this->collections()->count())
            + (int) ($this->concert_release_groups_count ?? $this->concertReleaseGroups()->count());
    }
}
