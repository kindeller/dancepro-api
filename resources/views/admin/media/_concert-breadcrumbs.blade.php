@php
    $galleryBreadcrumbs = [
        ['label' => 'Studios', 'url' => route('admin.concert-photo-gallery.events')],
        ['label' => $event->studio?->name ?? 'Unlinked events', 'url' => route('admin.concert-photo-gallery.events', $event->studio ? ['studio' => $event->studio->uuid] : ['unlinked' => 1])],
        ['label' => $event->concertBookingItem?->title ?: $event->name, 'url' => route('admin.concert-photo-gallery.show', $event)],
    ];
    if ($breadcrumbAlbum ?? null) $galleryBreadcrumbs[] = ['label' => $breadcrumbAlbum->name];
    if ($breadcrumbTopPics ?? false) $galleryBreadcrumbs[] = ['label' => 'Top Pics'];
@endphp
<x-gallery-breadcrumbs :items="$galleryBreadcrumbs" />
