<?php

namespace App\Features\Concerts\Services;

use App\Features\Concerts\Models\Concert;
use Illuminate\Http\Request;

class ConcertAccessSession
{
    public function allows(Request $request, Concert $concert): bool
    {
        return ! $concert->requiresPassword()
            || (bool) $request->session()->get("concert_access.{$concert->uuid}", false)
            || ($request->hasValidSignature() && $request->boolean('access'));
    }
}
