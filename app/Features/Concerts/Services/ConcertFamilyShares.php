<?php

namespace App\Features\Concerts\Services;

use App\Features\Concerts\Models\Concert;
use App\Features\Concerts\Models\ConcertFamilyShare;
use App\Features\Concerts\Notifications\ConcertFamilyVerification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class ConcertFamilyShares
{
    public function __construct(private readonly ConcertFamilyAccess $access) {}

    public function ownerPage(User $user, Concert $concert): array
    {
        abort_unless($user->canAccessCustomer() && ($concert->accessGrants()->withTrashed()->where('user_id', $user->id)->exists()
            || ConcertFamilyShare::where('user_id', $user->id)->where('concert_id', $concert->id)->exists()), 403);
        $canShare = $this->access->canShare($user, $concert);

        return ['concert' => $concert, 'canShare' => $canShare, 'assets' => $canShare ? $this->access->assets($concert)->get() : collect(),
            'shares' => ConcertFamilyShare::where('user_id', $user->id)->where('concert_id', $concert->id)->with('videos.asset')->latest()->get(),
            'access' => $this->access, 'managing' => false];
    }

    public function managerPage(User $user, Concert $concert): array
    {
        abort_unless($this->access->canManage($user, $concert), 403);

        return ['concert' => $concert, 'canShare' => false, 'assets' => collect(), 'managing' => true, 'access' => $this->access,
            'shares' => ConcertFamilyShare::where('concert_id', $concert->id)->with(['user', 'videos.asset'])->latest()->paginate(30)];
    }

    public function updatePolicy(User $user, Concert $concert, array $input): void
    {
        abort_unless($this->access->canManage($user, $concert), 403);
        $concert->studio->forceFill($input)->save();
        if (! $input['family_sharing_enabled']) {
            ConcertFamilyShare::whereHas('concert', fn ($q) => $q->where('studio_id', $concert->studio_id))
                ->update(['revoked_at' => now(), 'verified_session_hash' => null, 'code_hash' => null]);
        }
    }

    public function create(User $user, Concert $concert, array $input): ConcertFamilyShare
    {
        abort_unless($this->access->canShare($user, $concert), 403);

        return DB::transaction(function () use ($user, $concert, $input) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $query = ConcertFamilyShare::where('user_id', $user->id)->where('concert_id', $concert->id);
            if ((clone $query)->where('recipient_hash', hash('sha256', $input['recipient_email']))->exists()) {
                throw ValidationException::withMessages(['recipient_email' => 'An invitation already exists for this recipient. Use Renew on that invitation; download allowances are retained.']);
            }
            if ($query->count() >= 3) {
                throw ValidationException::withMessages(['recipient_email' => 'Three family recipients are already recorded for this concert. Contact DancePro if you need help.']);
            }
            if (strcasecmp($input['recipient_email'], $user->email) === 0) {
                throw ValidationException::withMessages(['recipient_email' => 'Use your family member’s email address. Your own access is already in My Media.']);
            }
            $assets = $this->access->assets($concert)->whereIn('uuid', $input['assets'])->get();
            if ($assets->count() !== count($input['assets'])) {
                throw ValidationException::withMessages(['assets' => 'Select only currently released videos from this concert.']);
            }
            $share = ConcertFamilyShare::create(['concert_id' => $concert->id, 'user_id' => $user->id,
                'recipient_name' => $input['recipient_name'], 'recipient_email' => $input['recipient_email'], 'recipient_hash' => hash('sha256', $input['recipient_email']), 'invitation_expires_at' => now()->addDays(14)]);
            foreach ($assets as $asset) {
                $share->videos()->create(['media_asset_id' => $asset->id]);
            }

            return $share;
        });
    }

    public function renew(User $user, ConcertFamilyShare $share): void
    {
        abort_unless(! $share->contact_redacted_at && $share->user_id === $user->id && $this->access->canShare($user, $share->concert), 403);
        DB::transaction(function () use ($share) {
            $share = ConcertFamilyShare::whereKey($share->id)->lockForUpdate()->firstOrFail();
            $share->forceFill(['accepted_at' => null, 'expires_at' => null, 'revoked_at' => null,
                'invitation_expires_at' => now()->addDays(14), 'version' => $share->version + 1, 'link_count' => $share->link_count + 1,
                'code_hash' => null, 'verified_session_hash' => null, 'challenge_session_hash' => null, 'player_hash' => null, 'player_expires_at' => null])->save();
        });
    }

    public function revoke(ConcertFamilyShare $share): void
    {
        $share->forceFill(['revoked_at' => now(), 'verified_session_hash' => null, 'code_hash' => null])->save();
    }

    public function sendCode(Request $request, ConcertFamilyShare $share): void
    {
        abort_unless($this->access->available($share), 410);
        $key = 'family-code:'.hash('sha256', $share->recipient_email);
        if (RateLimiter::tooManyAttempts($key, 10)) {
            throw ValidationException::withMessages(['code' => 'Too many code requests. Please try again in an hour.']);
        }
        RateLimiter::hit($key, 3600);
        $code = (string) random_int(100000, 999999);
        DB::transaction(function () use ($request, $share, $code) {
            $share = ConcertFamilyShare::whereKey($share->id)->lockForUpdate()->firstOrFail();
            if ($share->code_sent_at?->gt(now()->subMinute())) {
                throw ValidationException::withMessages(['code' => 'Please wait one minute before requesting another code.']);
            }
            $share->forceFill(['code_hash' => Hash::make($code), 'code_sent_at' => now(), 'code_expires_at' => now()->addMinutes(10),
                'code_attempts' => 0, 'challenge_session_hash' => $this->access->browserHash($request)])->save();
        });
        Notification::route('mail', $share->recipient_email)->notify(new ConcertFamilyVerification($code));
    }

    public function verify(Request $request, ConcertFamilyShare $share, string $code): void
    {
        abort_unless($this->access->available($share), 410);
        $valid = DB::transaction(function () use ($request, $share, $code) {
            $share = ConcertFamilyShare::whereKey($share->id)->lockForUpdate()->firstOrFail();
            if (! $share->code_hash || ! $share->code_expires_at?->isFuture() || $share->code_attempts >= 5
                || ! hash_equals($share->challenge_session_hash ?? '', $this->access->browserHash($request))) {
                return false;
            }
            $share->increment('code_attempts');
            if (! Hash::check($code, $share->code_hash)) {
                return false;
            }
            $request->session()->regenerate();
            $share->forceFill(['accepted_at' => $share->accepted_at ?? now(), 'expires_at' => $share->expires_at ?? now()->addDays(30),
                'verified_session_hash' => $this->access->browserHash($request), 'player_hash' => null, 'player_expires_at' => null, 'code_hash' => null, 'challenge_session_hash' => null])->save();
            $request->session()->put('concert_family.'.$share->uuid, $share->version);

            return true;
        });
        if (! $valid) {
            throw ValidationException::withMessages(['code' => 'That code is incorrect, expired or belongs to another browser. Request a new code after five attempts.']);
        }
    }
}
