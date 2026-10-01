<div class="gallery-navigation-grid">
    @foreach($routines as $routine)
        <x-gallery-navigation-card :title="$routine['label']"
            :image="($routine['cover_uuid'] ?? null) ? route($competitionRoutes['image'] ?? 'customer.competitions.image', ['schedulingEvent' => $event, 'photo' => $routine['cover_uuid'], 'size' => 'cover']) : null"
            :subtitle="collect([$routine['day'], $routine['section']])->filter()->implode(' · ')"
            :href="route($competitionRoutes['show'], ['schedulingEvent' => $event, 'folder' => $routine['uuid']]).'#gallery-content'" action="View routine">
            @if(isset($routine['dancers']))<p class="gallery-nav-subtitle">Matched: {{ implode(', ', $routine['dancers']) }}</p>@endif
            @if(isset($routine['studio']))<p class="gallery-nav-subtitle">{{ $routine['studio'] }}</p>@endif
        </x-gallery-navigation-card>
    @endforeach
</div>
