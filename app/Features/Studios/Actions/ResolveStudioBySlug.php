<?php

namespace App\Features\Studios\Actions;

use App\Features\Studios\Models\Studio;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ResolveStudioBySlug
{
    public function execute(string $slug): Studio
    {
        $studios = Studio::query()
            ->where('slug', $slug)
            ->limit(2)
            ->get();

        if ($studios->count() !== 1) {
            throw (new ModelNotFoundException)->setModel(Studio::class);
        }

        return $studios->sole();
    }
}
