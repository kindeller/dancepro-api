<?php

namespace App\Features\Concerts\Services;

use App\Features\Concerts\Models\Concert;
use App\Features\Customers\Services\CustomerConcertAccess;
use Illuminate\Http\Request;

class ConcertAccessSession
{
    public function __construct(private readonly CustomerConcertAccess $customerAccess) {}

    public function allows(Request $request, Concert $concert): bool
    {
        if ($concert->concert_code_group_id !== null && ! $concert->codeGroup?->downloadEndsAt()?->isFuture()) {
            return false;
        }
        if ($request->user() && $concert->accessGrants()->withTrashed()->where('user_id', $request->user()->id)->where('source', 'password')->exists()) {
            return $this->customerAccess->allows($request->user(), $concert);
        }

        return ! $concert->requiresPassword()
            || (bool) $request->session()->get("concert_access.{$concert->uuid}", false)
            || ($request->hasValidSignature() && $request->boolean('access'));
    }

    public function allowsStreaming(Request $request, Concert $concert): bool
    {
        if ($concert->concert_code_group_id !== null && ! $concert->codeGroup?->streamEndsAt()?->isFuture()) {
            return false;
        }
        if ($request->user() && $concert->accessGrants()->withTrashed()->where('user_id', $request->user()->id)->where('source', 'password')->exists()) {
            return $this->customerAccess->allowsStreaming($request->user(), $concert);
        }

        return $this->allows($request, $concert);
    }
}
