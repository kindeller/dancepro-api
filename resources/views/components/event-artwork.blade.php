@props(['url' => null, 'name', 'size' => 'thumbnail'])

<div {{ $attributes->class(['event-artwork', 'event-artwork-large' => $size === 'large']) }}>
    @if($url)
        <img src="{{ $url }}" alt="{{ $name }} logo" loading="lazy">
    @endif
        <svg style="{{ $url ? 'display:none' : '' }}" viewBox="0 0 24 24" role="img" aria-label="No artwork for {{ $name }}"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8" cy="8" r="1.5"/><path d="m4 18 5-5 3 3 4-5 4 7"/></svg>
</div>

@once
    @push('styles')
        <style nonce="{{ request()->attributes->get('csp_nonce') }}">
            .booking-event-heading { display:flex; align-items:center; gap:12px; }
            .event-artwork { display:grid; place-items:center; flex-shrink:0; width:84px; height:56px; overflow:hidden; border:1px solid #dce4e8; border-radius:8px; background:#fff; }
            .event-artwork img { display:block; width:100%; height:100%; object-fit:contain; }
            .event-artwork svg { width:28px; height:28px; fill:none; stroke:#8a9ba5; stroke-width:1.5; }
            .event-artwork-large { width:min(300px,100%); height:180px; margin:auto; }
            .event-artwork-large svg { width:48px; height:48px; }
        </style>
    @endpush
@endonce

@once
    @push('scripts')
        <script nonce="{{ request()->attributes->get('csp_nonce') }}">
            document.querySelectorAll('.event-artwork img').forEach(image => {
                const showFallback = () => { image.hidden = true; image.style.display = 'none'; image.nextElementSibling.style.display = ''; };
                image.addEventListener('error', showFallback);
                if (image.complete && image.naturalWidth === 0) showFallback();
            });
        </script>
    @endpush
@endonce
