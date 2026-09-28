<?php

namespace App\Features\Publicity\Services;

use App\Features\Customers\Models\CustomerAccountActivity;
use App\Features\Customers\Models\CustomerCompetitionRegistration;
use App\Features\Customers\Models\CustomerStudio;
use App\Features\Programmes\Models\CompetitionGallerySetting;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

class PublicityEventAccess
{
    public function version(SchedulingEvent $event): ?string
    {
        if ($event->cancelled_at) {
            return null;
        }
        $code = match ($event->event_type->value) {
            'competition' => CompetitionGallerySetting::where('scheduling_event_id', $event->id)->value('login_code_hash'),
            'concert' => $event->studio?->status->value === 'active' ? $event->studio->gallery_code_hash : null,
            default => null,
        };

        return $code ? hash('sha256', $event->uuid.':'.$code) : null;
    }

    public function link(SchedulingEvent $event): ?string
    {
        $version = $this->version($event);

        return $version ? rtrim(config('app.url'), '/').URL::signedRoute('publicity.join', ['schedulingEvent' => $event, 'version' => $version], absolute: false) : null;
    }

    public function validate(SchedulingEvent $event, string $version): void
    {
        $current = $this->version($event);
        abort_unless($current && hash_equals($current, $version), 403, 'This gallery link is no longer available. Ask the organiser for the current access details.');
    }

    public function studioVersion(Studio $studio): ?string
    {
        return $studio->status->value === 'active' && $studio->gallery_code_hash
            ? hash('sha256', $studio->uuid.':'.$studio->gallery_code_hash) : null;
    }

    public function studioLink(Studio $studio): ?string
    {
        $version = $this->studioVersion($studio);

        return $version ? rtrim(config('app.url'), '/').URL::signedRoute('publicity.studio.join', ['studio' => $studio, 'version' => $version], absolute: false) : null;
    }

    public function validateStudio(Studio $studio, string $version): void
    {
        $current = $this->studioVersion($studio);
        abort_unless($current && hash_equals($current, $version), 403, 'This studio link is no longer available. Ask the studio for the current access details.');
    }

    public function add(User $user, array $pending): string
    {
        abort_unless($user->canAccessCustomer(), 403);

        return DB::transaction(function () use ($user, $pending): string {
            $event = null;
            if (isset($pending['studio'])) {
                $studio = Studio::where('uuid', $pending['studio'])->lockForUpdate()->firstOrFail();
                $this->validateStudio($studio, $pending['version']);
            } else {
                $event = SchedulingEvent::where('uuid', $pending['event'])->lockForUpdate()->firstOrFail();
                $this->validate($event, $pending['version']);
                $studio = $event->studio;
            }
            $name = $event?->name ?? $studio->name;
            $competition = $event?->event_type->value === 'competition';
            // Serialize repeated scans for the same account; never create duplicate access.
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($competition) {
                $access = CustomerCompetitionRegistration::firstOrNew(['user_id' => $user->id, 'scheduling_event_id' => $event->id]);
            } else {
                $access = CustomerStudio::firstOrNew(['user_id' => $user->id, 'studio_id' => $studio->id]);
            }
            if (! $access->access_verified_at) {
                $access->access_verified_at = now();
                $access->save();
                CustomerAccountActivity::create(['user_id' => $user->id, 'actor_user_id' => $user->id,
                    'type' => 'publicity_qr_access_added', 'summary' => $name.' added through a gallery link or QR code.',
                    'metadata' => ['event_uuid' => $event?->uuid, 'studio_uuid' => $studio?->uuid, 'scope' => $competition ? 'competition_gallery' : 'studio_photo_galleries']]);
            }

            return $name;
        });
    }
}
