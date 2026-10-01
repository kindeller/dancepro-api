<?php

namespace App\Features\Studios\Models;

use App\Features\Concerts\Models\Concert;
use App\Features\Studios\Support\StudioStatus;
use App\Shared\Models\HasPublicUuid;
use App\Shared\Support\LocalMediaUrl;
use Database\Factories\StudioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

#[Fillable(['uuid', 'name', 'is_school', 'code', 'slug', 'status', 'description', 'cover_image_url', 'cover_image_storage_key', 'cover_image_revision', 'cover_image_mime_type', 'logo_path', 'brand_color', 'contact_name', 'contact_email', 'contact_phone', 'legacy_id', 'notes'])]
class Studio extends Model
{
    /** @use HasFactory<StudioFactory> */
    use HasFactory, HasPublicUuid, SoftDeletes;

    protected $hidden = ['gallery_code_hash', 'gallery_code_encrypted'];

    /** Studio code is the shared membership and concert-photo code. */
    public function matchesPhotoAccessCode(string $code): bool
    {
        if (filled($this->code)) {
            return hash_equals(mb_strtoupper(trim($this->code)), mb_strtoupper(trim($code)));
        }

        // Preserve hash-only legacy studios until an administrator sets their Studio code.
        return $this->gallery_code_hash && Hash::check(mb_strtoupper(trim($code)), $this->gallery_code_hash);
    }

    public function photoAccessFingerprint(): ?string
    {
        $source = filled($this->code) ? mb_strtoupper(trim($this->code)) : $this->gallery_code_hash;

        return $source ? hash('sha256', $source) : null;
    }

    public function concerts(): HasMany
    {
        return $this->hasMany(Concert::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(StudioContact::class)->orderBy('position')->orderBy('id');
    }

    public function accountAccesses(): HasMany
    {
        return $this->hasMany(StudioAccountAccess::class)->orderBy('email');
    }

    /** @return list<string> */
    public function contactEmailAddresses(): array
    {
        return $this->contacts
            ->flatMap(fn (StudioContact $contact): array => $contact->emailAddresses())
            ->unique()
            ->values()
            ->all();
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk(config('uploads.public_disk'))->url($this->logo_path) : null;
    }

    public function coverImageForDisplay(): ?string
    {
        return app(LocalMediaUrl::class)->display($this->cover_image_url);
    }

    protected function casts(): array
    {
        return ['family_sharing_enabled' => 'boolean', 'family_downloads_enabled' => 'boolean', 'is_school' => 'boolean', 'status' => StudioStatus::class, 'gallery_code_version' => 'integer', 'gallery_code_encrypted' => 'encrypted'];
    }

    protected static function newFactory(): StudioFactory
    {
        return StudioFactory::new();
    }
}
