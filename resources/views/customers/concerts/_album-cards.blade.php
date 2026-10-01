@if($collections->isNotEmpty())
    <div class="gallery-navigation-grid concert-albums">
        @foreach($collections as $album)
            @php($albumCover = $album->displayCoverAsset())
            <x-gallery-navigation-card class="concert-folder" :title="$album->name"
                :image="$albumCover ? route($concertRoutes['image'], ['concert' => $concert, 'asset' => $albumCover->uuid, 'size' => 'cover']) : null"
                :subtitle="$album->visible_photo_count.' '.Str::plural('photo', $album->visible_photo_count)"
                :href="route($concertRoutes['show'], array_filter(['concert' => $concert, 'group' => $selectedGroup?->uuid, 'folder' => $album->uuid])).'#gallery-content'"
                action="View album">
                @if($selectedGroup && $selectedGroup->collections->pluck('concert_id')->unique()->count() > 1)
                    <p class="gallery-nav-subtitle">{{ $album->concert->name }}</p>
                @endif
            </x-gallery-navigation-card>
        @endforeach
    </div>
@else
    <div class="empty"><p>Concert photos are being prepared.</p></div>
@endif
