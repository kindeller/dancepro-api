@props(['photo', 'priority' => false, 'sizes' => '(max-width: 700px) 100vw, 65vw'])
@php($photograph = config('website.photography.'.$photo))
@if($photograph)
<img {{ $attributes }} src="{{ asset('images/website/'.$photo.'-1280.webp') }}"
     srcset="{{ asset('images/website/'.$photo.'-640.webp') }} 640w, {{ asset('images/website/'.$photo.'-1280.webp') }} 1280w, {{ asset('images/website/'.$photo.'-2200.webp') }} 2200w"
     sizes="{{ $sizes }}" width="{{ $photograph['width'] }}" height="{{ $photograph['height'] }}"
     alt="{{ $photograph['alt'] }}" loading="{{ $priority ? 'eager' : 'lazy' }}" fetchpriority="{{ $priority ? 'high' : 'auto' }}" decoding="async">
@endif
