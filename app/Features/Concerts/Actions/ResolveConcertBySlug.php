<?php

namespace App\Features\Concerts\Actions;

use App\Features\Concerts\Models\Concert;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ResolveConcertBySlug
{
    public function execute(string $slug): Concert
    {
        $concerts = Concert::query()
            ->where('slug', $slug)
            ->limit(2)
            ->get();

        if ($concerts->count() !== 1) {
            throw (new ModelNotFoundException)->setModel(Concert::class);
        }

        return $concerts->sole();
    }
}
