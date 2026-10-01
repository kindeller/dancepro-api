<?php

namespace App\Features\Studios\Actions;

use App\Features\Studios\Models\Studio;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SaveStudioGalleryCode
{
    public function execute(Studio $studio, User $actor, string $code, int $version): void
    {
        DB::transaction(function () use ($studio, $actor, $code, $version): void {
            $studio = Studio::whereKey($studio->id)->lockForUpdate()->firstOrFail();
            abort_unless($studio->gallery_code_version === $version, 409, 'The studio gallery code changed. Reload this page.');
            $studio->forceFill(['code' => strtoupper(trim($code)), 'gallery_code_hash' => Hash::make(strtoupper(trim($code))),
                'gallery_code_encrypted' => strtoupper(trim($code)), 'gallery_code_version' => $version + 1])->save();
            DB::table('studio_gallery_code_changes')->insert(['studio_id' => $studio->id,
                'actor_user_id' => $actor->id, 'version' => $version + 1, 'created_at' => now()]);
        });
    }
}
