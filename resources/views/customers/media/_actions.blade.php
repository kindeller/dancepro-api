@once
@push('styles')
<style>
    .media-account-actions { display:flex; justify-content:flex-end; gap:9px; margin-bottom:22px; }
    .media-account-actions a { display:inline-flex; align-items:center; gap:8px; }
    .media-account-actions svg { width:19px; height:19px; fill:none; stroke:currentColor; stroke-width:1.8; stroke-linecap:round; stroke-linejoin:round; }
    .media-account-count { display:grid; min-width:23px; height:23px; place-items:center; padding:0 6px; border-radius:999px; background:#087bab; color:#fff; font-size:12px; }
</style>
@endpush
@endonce
<nav class="media-account-actions" aria-label="Saved media"><a class="button secondary" href="{{ route('customer.media.favourites') }}">♡ Favourites @if($selectionCounts['favourites'])<span class="media-account-count">{{ $selectionCounts['favourites'] }}</span>@endif</a><a class="button secondary" href="{{ route('customer.media.cart') }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.1 11h11.5l2-8H6"/><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>Cart @if($selectionCounts['cart'])<span class="media-account-count">{{ $selectionCounts['cart'] }}</span>@endif</a></nav>
