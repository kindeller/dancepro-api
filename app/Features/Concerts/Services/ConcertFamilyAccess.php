<?php

namespace App\Features\Concerts\Services;

use App\Features\Concerts\Models\Concert;
use App\Features\Concerts\Models\ConcertFamilyShare;
use App\Features\Concerts\Models\ConcertFamilyVideo;
use App\Features\Customers\Services\CustomerConcertAccess;
use App\Features\Media\Models\MediaAsset;
use App\Features\Studios\Models\StudioAccountAccess;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ConcertFamilyAccess
{
    public function __construct(private readonly CustomerConcertAccess $access) {}

    public function canShare(User $user, Concert $concert): bool
    {
        return $user->is_active && $user->hasVerifiedEmail() && $concert->studio?->family_sharing_enabled
            && $this->access->allows($user, $concert);
    }

    public function canManage(User $user, Concert $concert): bool
    {
        if (! $user->is_active || ! $user->hasVerifiedEmail()) {
            return false;
        }

        return $user->canAccessAdmin() || StudioAccountAccess::where('studio_id', $concert->studio_id)
            ->where(fn ($query) => $query->where('user_id', $user->id)->orWhereRaw('lower(email) = ?', [mb_strtolower($user->email)]))->where('status', 'active')->whereIn('role', ['owner', 'director'])->exists();
    }

    public function end(ConcertFamilyShare $share, bool $streaming = false): CarbonImmutable
    {
        $end = ($share->expires_at ?? $share->invitation_expires_at)->toImmutable();
        $concert = $share->concert;
        if ($concert->available_until) {
            $end = $end->min($concert->available_until);
        }
        if ($concert->concert_code_group_id) {
            $groupEnd = $streaming ? $concert->codeGroup?->streamEndsAt() : $concert->codeGroup?->downloadEndsAt();
            if (! $groupEnd) {
                return CarbonImmutable::now()->subSecond();
            }
            $end = $end->min($groupEnd);
        }
        $grantEnd = $this->access->grants($share->user)->where('concert_id', $concert->id)->get()
            ->filter(fn ($grant) => $this->access->matches($grant, $concert));
        if ($grantEnd->isNotEmpty() && ! $grantEnd->contains(fn ($grant) => $grant->expires_at === null)) {
            $end = $end->min($grantEnd->max('expires_at'));
        }

        return $end;
    }

    public function available(ConcertFamilyShare $share): bool
    {
        return ! $share->contact_redacted_at && ! $share->revoked_at && $share->user && $this->canShare($share->user, $share->concert)
            && $this->end($share)->isFuture();
    }

    public function browserHash(Request $request): string
    {
        if (! $request->session()->has('concert_family_browser')) {
            $request->session()->put('concert_family_browser', Str::random(64));
        }

        return hash('sha256', $request->session()->get('concert_family_browser'));
    }

    public function verified(Request $request, ConcertFamilyShare $share): bool
    {
        return $this->available($share) && $share->accepted_at && $share->verified_session_hash
            && hash_equals($share->verified_session_hash, $this->browserHash($request))
            && (int) $request->session()->get('concert_family.'.$share->uuid) === (int) $share->version;
    }

    public function assertVerified(Request $request, ConcertFamilyShare $share): void
    {
        abort_unless($this->available($share), 410, 'This family invitation has expired or is no longer available.');
        abort_unless($this->verified($request, $share), 403, 'Verify your email to open this family invitation.');
    }

    public function video(Request $request, ConcertFamilyShare $share, MediaAsset $asset, bool $streaming = false): ConcertFamilyVideo
    {
        $this->assertVerified($request, $share);
        if ($streaming) {
            abort_unless($this->end($share, true)->isFuture() && $this->access->allowsStreaming($share->user, $share->concert), 403, 'Streaming access has ended.');
        }
        abort_unless($this->assets($share->concert)->whereKey($asset->id)->exists(), 404);

        return $share->videos()->where('media_asset_id', $asset->id)->firstOrFail();
    }

    public function assets(Concert $concert)
    {
        return MediaAsset::whereHas('collection', fn ($query) => $query->where('concert_id', $concert->id)->where('status', 'published'))
            ->where('media_type', 'video')->where('status', 'available')->where('is_visible', true)->orderBy('sort_order');
    }
}
