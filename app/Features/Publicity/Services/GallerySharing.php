<?php

namespace App\Features\Publicity\Services;

use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;

class GallerySharing
{
    public function __construct(private readonly PublicityEventAccess $access) {}

    public function catalogue(array $filters): array
    {
        $type = $filters['type'] ?? 'competition';
        $query = $type === 'studio'
            ? Studio::query()->where('status', 'active')->orderBy('name')
            : SchedulingEvent::query()->where('event_type', 'competition')->whereNull('cancelled_at')->orderByDesc('event_date')->orderByDesc('id');
        $search = trim($filters['q'] ?? '');
        $entries = $query->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))->paginate(12)->withQueryString();
        $entries->through(function ($entry) use ($type) {
            $studio = $type === 'studio';

            return ['name' => $entry->name, 'date' => $studio ? null : $entry->event_date?->format('j M Y'),
                'url' => $studio ? $this->access->studioLink($entry) : $this->access->link($entry),
                'settings' => route($studio ? 'admin.studios.edit' : 'admin.competition-gallery.settings', $entry),
                'qr' => route($studio ? 'admin.publicity.sharing.studio-qr' : 'admin.publicity.sharing.event-qr', $entry)];
        });

        return compact('type', 'entries', 'search');
    }

    public function qr(?string $url): string
    {
        abort_unless($url, 404, 'Configure the gallery access code before sharing its QR code.');

        return (new Writer(new GDLibRenderer(1000, 4)))->writeString($url);
    }
}
