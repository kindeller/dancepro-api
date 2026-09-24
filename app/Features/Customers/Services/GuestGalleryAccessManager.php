<?php

namespace App\Features\Customers\Services;

use App\Features\Concerts\Models\Concert;
use App\Features\Customers\Models\GuestGalleryAccess;
use App\Features\Programmes\Models\CompetitionGallerySetting;
use App\Features\Scheduling\Models\SchedulingEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class GuestGalleryAccessManager
{
    public function __construct(private readonly GuestGalleryCatalogue $catalogue) {}

    public function unlockCompetition(Request $request, SchedulingEvent $event, string $email, string $code): GuestGalleryAccess
    {
        abort_unless($this->catalogue->competitionIsReleased($event), 404);
        $hash = CompetitionGallerySetting::where('scheduling_event_id', $event->id)->value('login_code_hash');
        $this->verifyCode($hash, $code);

        return $this->create($request, 'competition', $email, event: $event);
    }

    public function unlockConcert(Request $request, Concert $concert, string $email, string $code): GuestGalleryAccess
    {
        abort_unless($this->catalogue->concertIsReleased($concert), 404);
        $this->verifyCode($concert->studio->gallery_code_hash, $code);

        return $this->create($request, 'concert', $email, concert: $concert);
    }

    public function competition(Request $request, SchedulingEvent $event): GuestGalleryAccess
    {
        return $this->find($request, 'competition', $event->id, null);
    }

    public function concert(Request $request, Concert $concert): GuestGalleryAccess
    {
        return $this->find($request, 'concert', null, $concert->id);
    }

    private function create(Request $request, string $type, string $email, ?SchedulingEvent $event = null, ?Concert $concert = null): GuestGalleryAccess
    {
        $email = mb_strtolower(trim($email));
        $emailHash = hash('sha256', $email);
        $access = DB::transaction(function () use ($type, $event, $concert, $email, $emailHash): GuestGalleryAccess {
            $access = GuestGalleryAccess::query()->where('gallery_type', $type)->where('email_hash', $emailHash)
                ->when($event, fn ($query) => $query->where('scheduling_event_id', $event->id))
                ->when($concert, fn ($query) => $query->where('concert_id', $concert->id))
                ->lockForUpdate()->first();
            if (! $access) {
                $access = GuestGalleryAccess::create([
                    'gallery_type' => $type, 'scheduling_event_id' => $event?->id, 'concert_id' => $concert?->id,
                    'email_hash' => $emailHash, 'email' => $email, 'expires_at' => now()->addDays(30),
                    'last_used_at' => now(),
                ]);
            }
            $access->update(['email' => $email, 'expires_at' => now()->addDays(30), 'last_used_at' => now()]);

            return $access;
        });
        $ids = array_values(array_unique(array_merge($request->session()->get('guest_gallery_accesses', []), [$access->uuid])));
        $request->session()->put('guest_gallery_accesses', array_slice($ids, -30));
        $request->session()->forget('guest_gallery_prefill_email');

        return $access;
    }

    private function find(Request $request, string $type, ?int $eventId, ?int $concertId): GuestGalleryAccess
    {
        $ids = $request->session()->get('guest_gallery_accesses', []);
        $access = GuestGalleryAccess::whereIn('uuid', $ids)->where('gallery_type', $type)
            ->when($eventId, fn ($query) => $query->where('scheduling_event_id', $eventId))
            ->when($concertId, fn ($query) => $query->where('concert_id', $concertId))
            ->where('expires_at', '>', now())->latest('id')->first();
        abort_unless($access, 403, 'Enter your email and gallery login code to view this gallery.');
        $access->update(['last_used_at' => now()]);

        return $access;
    }

    private function verifyCode(?string $hash, string $code): void
    {
        if (! $hash || ! Hash::check(strtoupper(trim($code)), $hash)) {
            throw ValidationException::withMessages(['login_code' => 'The gallery or login code is incorrect. Please check and try again.']);
        }
    }
}
