<?php

namespace App\Features\Publicity\Services;

use App\Features\Media\Services\AdminTopPics;
use App\Features\Publicity\Models\PublicitySlide;
use App\Features\Publicity\Models\TvScreen;
use App\Features\Publicity\Models\TvSlideshow;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SlideshowManagement
{
    public function overview(string $type, AdminTopPics $pics, string $advertisingType = 'competition'): array
    {
        $counts = $pics->files()->selectRaw('galleries.scheduling_event_id, COUNT(*) as top_pic_count, MIN(programme_photo_files.uuid) as cover_uuid')
            ->groupBy('galleries.scheduling_event_id');
        $events = SchedulingEvent::query()->leftJoinSub($counts, 'top_pics', 'top_pics.scheduling_event_id', '=', 'scheduling_events.id')
            ->where('event_type', $type)->select('scheduling_events.*', 'top_pics.top_pic_count', 'top_pics.cover_uuid')
            ->orderByDesc('event_date')->orderByDesc('scheduling_events.id')->paginate(24)->withQueryString();

        return compact('events', 'type', 'advertisingType') + [
            'slides' => PublicitySlide::whereNull('scheduling_event_id')->where('audience', $advertisingType)->orderBy('sort_order')->orderBy('id')->get(),
            'screens' => TvScreen::whereNotNull('paired_at')->with('event')->latest()->get(),
        ];
    }

    public function eventPage(SchedulingEvent $event, SlideshowPlaylist $playlist): array
    {
        return ['event' => $event, 'type' => $event->event_type->value,
            'eventQrAvailable' => app(PublicityEventAccess::class)->version($event) !== null,
            'settings' => $this->settings($event), 'playlist' => $playlist->manifest($event, true),
            'slides' => PublicitySlide::where('scheduling_event_id', $event->id)->orderBy('sort_order')->orderBy('id')->get(),
            'screens' => TvScreen::where('scheduling_event_id', $event->id)->whereNotNull('paired_at')->with('event')->latest()->get(),
        ];
    }

    public function updateSlide(PublicitySlide $slide, array $data, User $actor): void
    {
        $event = $slide->scheduling_event_id ? SchedulingEvent::findOrFail($slide->scheduling_event_id) : null;
        $this->saveSlide($event, $data, $actor, $slide);
    }

    public function revoke(TvScreen $screen): void
    {
        $screen->update(['revoked_at' => now()]);
    }

    public function settings(SchedulingEvent $event): TvSlideshow
    {
        abort_unless(in_array($event->event_type->value, ['competition', 'concert'], true), 404);

        return TvSlideshow::firstOrNew(['scheduling_event_id' => $event->id], ['enabled' => false, 'photo_seconds' => 2, 'promotion_every' => 5]);
    }

    public function saveSettings(SchedulingEvent $event, array $data): void
    {
        $this->settings($event)->fill($data)->save();
    }

    public function saveSlide(?SchedulingEvent $event, array $data, User $actor, ?PublicitySlide $slide = null): void
    {
        if ($event) {
            $this->settings($event);
        }
        $data['audience'] = $event?->event_type->value ?? ($data['audience'] ?? $slide?->audience ?? 'competition');
        $data['duration_seconds'] ??= $event ? 8 : 5;
        $image = $data['image'] ?? null;
        $video = $data['video'] ?? null;
        unset($data['image'], $data['video']);
        if ($video instanceof UploadedFile) {
            $data = array_replace($data, app(AdvertisingVideo::class)->store($video));
        }
        if ($image instanceof UploadedFile) {
            $data = array_replace($data, ['video_key' => null, 'video_hash' => null, 'video_bytes' => null, 'video_seconds' => null]);
            $bytes = file_get_contents($image->getRealPath());
            $info = @getimagesizefromstring($bytes);
            abort_unless($info && $info[0] * $info[1] <= 16000000, 422, 'Use an advertising image of at most 16 megapixels.');
            $source = @imagecreatefromstring($bytes);
            abort_unless($source, 422, 'The image could not be read.');
            $scale = min(1, 1920 / max(imagesx($source), imagesy($source)));
            $output = imagescale($source, max(1, (int) (imagesx($source) * $scale)), max(1, (int) (imagesy($source) * $scale)));
            abort_unless($output, 422, 'The image could not be resized.');
            ob_start();
            imagejpeg($output, null, 88);
            $jpeg = ob_get_clean();
            $data['image_key'] = 'publicity/slides/'.Str::uuid().'.jpg';
            $data['image_hash'] = hash('sha256', $jpeg);
            Storage::disk('local')->put($data['image_key'], $jpeg);
        }
        $slide ??= new PublicitySlide(['scheduling_event_id' => $event?->id, 'created_by' => $actor->id]);
        $slide->fill($data)->save();
    }

    public function beginPairing(): array
    {
        $token = bin2hex(random_bytes(32));
        do {
            $code = (string) random_int(100000, 999999);
            $hash = hash('sha256', $code);
        } while (TvScreen::where('pairing_code_hash', $hash)->exists());
        TvScreen::create(['token_hash' => hash('sha256', $token), 'pairing_code_hash' => $hash,
            'pairing_expires_at' => now()->addMinutes(10), 'token_expires_at' => now()->addMinutes(10)]);

        return ['token' => $token, 'code' => $code, 'expires_in' => 600];
    }

    public function pair(SchedulingEvent $event, array $data, User $actor): void
    {
        $this->settings($event);
        DB::transaction(function () use ($event, $data, $actor): void {
            $screen = TvScreen::where('pairing_code_hash', hash('sha256', $data['code']))->lockForUpdate()->first();
            if (! $screen || $screen->paired_at || $screen->revoked_at || $screen->pairing_expires_at->isPast()) {
                throw ValidationException::withMessages(['code' => 'This pairing code has expired or is incorrect. Generate a new code on the screen.']);
            }
            $screen->update(['name' => $data['name'], 'scheduling_event_id' => $event->id, 'paired_by' => $actor->id,
                'paired_at' => now(), 'pairing_code_hash' => null, 'token_expires_at' => now()->addDays(30)]);
        });
    }
}
