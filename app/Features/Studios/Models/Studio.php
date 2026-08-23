<?php

namespace App\Features\Studios\Models;

use App\Features\Concerts\Models\Concert;
use App\Features\Studios\Support\StudioStatus;
use App\Shared\Models\HasPublicUuid;
use Database\Factories\StudioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['uuid', 'name', 'slug', 'status', 'description', 'cover_image_url', 'brand_color', 'contact_name', 'contact_email', 'contact_phone', 'legacy_id', 'notes'])]
class Studio extends Model
{
    /** @use HasFactory<StudioFactory> */
    use HasFactory, HasPublicUuid, SoftDeletes;

    public function concerts(): HasMany
    {
        return $this->hasMany(Concert::class);
    }

    protected function casts(): array
    {
        return ['status' => StudioStatus::class];
    }

    protected static function newFactory(): StudioFactory
    {
        return StudioFactory::new();
    }
}
