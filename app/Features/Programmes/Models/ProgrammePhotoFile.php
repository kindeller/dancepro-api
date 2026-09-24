<?php

namespace App\Features\Programmes\Models;

use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ProgrammePhotoFile extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id'];

    public function scopeInGallery(Builder $query, ProgrammePhotoGallery $gallery): Builder
    {
        return $query->join('programme_photo_contributions as contributions', 'contributions.id', '=', 'programme_photo_files.programme_photo_contribution_id')
            ->where('programme_photo_files.programme_photo_gallery_id', $gallery->id)
            ->where('contributions.programme_upload_plan_id', $gallery->programme_upload_plan_id)
            ->where('programme_photo_files.status', 'verified')->whereNotNull('programme_photo_files.verified_at')
            ->whereNotNull('programme_photo_files.storage_key')->whereNull('programme_photo_files.duplicate_of_file_id')
            ->select('programme_photo_files.*');
    }

    protected function casts(): array
    {
        return ['byte_count' => 'integer', 'is_top_pic' => 'boolean', 'verified_at' => 'datetime'];
    }
}
