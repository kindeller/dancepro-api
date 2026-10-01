@props(['title', 'image' => null, 'logo' => false, 'subtitle' => null, 'href' => null, 'action' => 'View gallery'])

@if($href)
<a {{ $attributes->class(['card', 'gallery-navigation-card', 'gallery-hover-card']) }} href="{{ $href }}">
@else
<article {{ $attributes->class(['card', 'gallery-navigation-card', 'gallery-hover-card']) }}>
@endif
    <div class="gallery-nav-image {{ $logo ? 'is-logo' : '' }}">
        @if($image)<img src="{{ $image }}" alt="" loading="lazy">
        @else<span aria-hidden="true">{{ mb_substr($title, 0, 1) }}</span>@endif
    </div>
    <div class="gallery-nav-body">
        <h3 class="gallery-nav-title">{{ $title }}</h3>
        @if($subtitle)<p class="gallery-nav-subtitle">{{ $subtitle }}</p>@endif
        {{ $slot }}
    </div>
    <div class="gallery-nav-footer">
        @if(isset($actions)){{ $actions }}@else<span class="gallery-nav-action">{{ $action }} <span aria-hidden="true">→</span></span>@endif
    </div>
@if($href)</a>@else</article>@endif
