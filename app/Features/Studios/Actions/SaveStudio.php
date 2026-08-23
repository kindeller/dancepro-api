<?php

namespace App\Features\Studios\Actions;

use App\Features\Studios\Models\Studio;
use Illuminate\Support\Str;

class SaveStudio
{
    public function execute(array $attributes, ?Studio $studio = null): Studio
    {
        $studio ??= new Studio;
        $uuid = $studio->uuid ?? (string) Str::uuid();

        $studio->fill([
            ...$attributes,
            'uuid' => $uuid,
            'slug' => ($attributes['slug'] ?? null) ?: ($studio->slug ?: Str::slug($attributes['name']).'-'.Str::substr($uuid, 0, 8)),
        ])->save();

        return $studio;
    }
}
