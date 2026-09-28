<?php

namespace App\Features\Programmes\Actions;

use App\Features\Programmes\Models\ProgrammePhotoFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TidyDemoTopPics
{
    /** Retain photo identities used by favourites/orders; remove only redundant demo selections. */
    public function execute(): int
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('Demo Top Pics cleanup may only run locally.');
        }

        return DB::transaction(function (): int {
            $files = ProgrammePhotoFile::query()->where('storage_disk', 'local')
                ->where('storage_key', 'like', 'local-development/realistic-media/%')
                ->where('status', 'verified')->whereNotNull('verified_at')
                ->whereNull('duplicate_of_file_id')->orderBy('id')->lockForUpdate()->get();
            $groups = $files->groupBy(fn (ProgrammePhotoFile $file) => json_encode([
                $file->programme_photo_gallery_id, $file->programme_photo_contribution_id,
                $file->storage_key, $file->sha256, $file->byte_count,
            ], JSON_THROW_ON_ERROR));
            $cleared = 0;
            foreach ($groups as $group) {
                if ($group->count() < 2 || ! $group->contains('is_top_pic', true)) {
                    continue;
                }
                $canonical = $group->first();
                if (! $canonical->is_top_pic) {
                    $canonical->update(['is_top_pic' => true]);
                }
                foreach ($group->skip(1)->where('is_top_pic', true) as $duplicate) {
                    $duplicate->update(['is_top_pic' => false]);
                    $cleared++;
                }
            }

            return $cleared;
        });
    }
}
