<?php

namespace App\Features\Concerts\Actions;

use App\Features\Concerts\Models\Concert;
use App\Features\Concerts\Support\ConcertStatus;
use App\Features\Studios\Models\Studio;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class SaveConcert
{
    public function execute(array $attributes, User $staff, ?Concert $concert = null): Concert
    {
        $concert ??= new Concert;
        $uuid = $concert->uuid ?? (string) Str::uuid();
        $creating = ! $concert->exists;
        $password = Arr::pull($attributes, 'access_password');
        $clearPassword = (bool) Arr::pull($attributes, 'clear_access_password', false);
        $approved = (bool) Arr::pull($attributes, 'is_approved', false);

        $attributes['uuid'] = $uuid;
        $attributes['slug'] = ($attributes['slug'] ?? null) ?: ($concert->slug ?: Str::slug($attributes['name']).'-'.Str::substr($uuid, 0, 8));
        $attributes['is_enabled'] = (bool) ($attributes['is_enabled'] ?? false);
        $attributes['requires_approval'] = (bool) ($attributes['requires_approval'] ?? false);
        $attributes['approved_at'] = $attributes['requires_approval'] && $approved
            ? ($concert->approved_at ?? now())
            : null;
        $attributes['approved_by_user_id'] = $attributes['approved_at'] ? $staff->id : null;
        $attributes['updated_by_user_id'] = $staff->id;
        $attributes['published_at'] = $attributes['status'] === ConcertStatus::Published->value
            ? ($concert->published_at ?? now())
            : null;
        $attributes['archived_at'] = $attributes['status'] === ConcertStatus::Archived->value
            ? ($concert->archived_at ?? now())
            : null;

        if ($creating) {
            $studioUuid = Studio::query()->findOrFail($attributes['studio_id'])->uuid;
            $attributes['created_by_user_id'] = $staff->id;
            $attributes['storage_disk'] = 's3_concerts';
            $attributes['storage_prefix'] = "studios/{$studioUuid}/concerts/{$uuid}/";
        }

        if ($clearPassword) {
            $attributes['access_password_hash'] = null;
        } elseif (filled($password)) {
            $attributes['access_password_hash'] = $password;
        }

        $concert->fill($attributes)->save();

        return $concert;
    }
}
