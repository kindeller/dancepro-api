@props(['app', 'count' => 0, 'label' => null])
@php
    $destination = [
        'admin' => ['Admin Hub', 'admin.dashboard', 'dancepro-admin-icon.png'],
        'crew' => ['My Hub', 'crew.availability.index', 'dancepro-crew-hub-icon.png'],
        'media' => ['My Media', 'customer.media.index', 'dancepro-media-icon.png'],
    ][$app];
@endphp
<a {{ $attributes->class(['app-switch-link']) }} href="{{ route($destination[1]) }}">
    @if($count)<span class="app-switch-badge" aria-label="{{ $count }} items needing attention">{{ $count }}</span>@endif
    <img src="{{ asset('images/brand/'.$destination[2]) }}" width="24" height="24" alt="">
    <span>{{ $label ?? $destination[0] }}</span>
</a>
