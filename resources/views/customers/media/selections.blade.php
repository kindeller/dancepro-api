@extends('layouts.public', ['title' => $mode === 'favourites' ? 'Favourites' : 'Cart', 'footerLabel' => 'DancePro Media'])

@push('styles')
<style>
    .account-selection-nav { display:flex; align-items:center; gap:8px; margin-bottom:28px; }
    .account-selection-links { display:flex; gap:8px; margin-left:auto; }
    .account-selection-nav a { display:inline-flex; align-items:center; gap:8px; min-height:42px; padding:10px 15px; border:1px solid var(--line); border-radius:9px; background:#fff; color:var(--ink); font-weight:800; line-height:1; text-decoration:none; transition:border-color .18s ease,background-color .18s ease,color .18s ease,transform .18s ease; }
    .account-selection-nav svg { width:19px; height:19px; fill:none; stroke:currentColor; stroke-width:1.8; stroke-linecap:round; stroke-linejoin:round; }
    .account-selection-nav a:hover,.account-selection-nav a:focus-visible { border-color:#078bc4; background:#e7f7fe; color:#07688f; outline:0; transform:translateY(-1px); }
    .account-selection-nav a[aria-current="page"] { border-color:#078bc4; background:#e9f7fc; color:#07688f; }
    .event-selection-group { margin-bottom:20px; overflow:visible; }
    .event-selection-head { display:flex; align-items:center; gap:15px; padding:15px 18px; border-bottom:1px solid var(--line); }
    .event-selection-logo { width:64px; height:48px; flex:0 0 64px; border-radius:7px; background:#10212c; object-fit:contain; }
    .event-selection-head h2 { margin:0; font-size:20px; }
    .event-selection-head p { margin:3px 0 0; color:var(--muted); }
    .event-selection-choice { display:flex; align-items:center; gap:9px; margin-left:auto; padding:9px 12px; border:1px solid #acd4e4; border-radius:9px; background:#eefaff; font-weight:800; cursor:pointer; }
    .event-selection-choice input { width:18px; min-height:0; height:18px; margin:0; accent-color:#078bc4; }
    .event-selection-group.is-deferred { opacity:.62; }
    .event-selection-group.is-deferred .event-selection-choice { border-color:var(--line); background:#f3f5f6; }
    .event-selection-items { display:grid; gap:10px; padding:14px; }
    .event-selection-items.is-favourites { grid-template-columns:repeat(auto-fill,minmax(190px,230px)); align-items:start; }
    .event-selection-item { display:flex; align-items:center; gap:13px; padding:10px; border:1px solid var(--line); border-radius:9px; background:#fff; }
    .event-selection-items.is-favourites .event-selection-item { display:flex; flex-direction:column; align-items:stretch; padding:0; overflow:hidden; }
    .event-selection-thumb { display:block; width:100px; height:75px; flex:0 0 100px; border-radius:6px; background:#14212a; object-fit:contain; }
    .event-selection-items.is-favourites .event-selection-thumb { width:100%; height:auto; aspect-ratio:4/3; border-radius:0; }
    .event-selection-copy { min-width:0; flex:1; }
    .event-selection-items.is-favourites .event-selection-copy { padding:2px 12px; }
    .event-selection-copy strong,.event-selection-copy span { display:block; }
    .event-selection-copy span { margin-top:3px; color:var(--muted); font-size:14px; }
    .event-selection-actions { display:flex; gap:7px; }
    .event-selection-actions form:has(.selection-icon.heart) { order:1; }
    .event-selection-actions form:has(.selection-icon.cart) { order:2; }
    .event-selection-items.is-favourites .event-selection-actions { padding:0 12px 12px; }
    .event-selection-actions form { margin:0; }
    .selection-icon { display:grid; width:39px; height:39px; place-items:center; border:1px solid #cbd9df; border-radius:8px; background:#fff; color:#285068; cursor:pointer; }
    .selection-icon svg { width:20px; height:20px; fill:none; stroke:currentColor; stroke-width:1.8; stroke-linecap:round; stroke-linejoin:round; }
    .selection-icon.cart { border-color:#87bfd7; background:#fff; color:#057eb2; }
    .selection-icon.cart.is-selected { border-color:#078bc4; background:#078bc4; color:#fff; }
    .selection-icon.heart { border-color:#a8bde7; background:#edf2ff; color:#335fc7; }
    .selection-icon.heart svg { fill:currentColor; }
    .selection-icon.bin { border-color:#e6b5bb; background:#fff3f4; color:#ad1d2c; }
    .event-selection-more { display:flex; justify-content:flex-end; padding:0 14px 14px; }
    .event-selection-more button { border:0; background:transparent; color:#0879a7; font:inherit; font-weight:800; cursor:pointer; }
    .event-selection-totals { padding:14px 18px; border-top:1px solid var(--line); background:#f7fafb; }
    .event-selection-totals p { display:flex; justify-content:space-between; gap:15px; margin:4px 0; }
    .account-selection-total { max-width:620px; margin-left:auto; padding:20px; }
    .account-selection-total p { display:flex; justify-content:space-between; gap:16px; margin:7px 0; }
    .account-selection-total .total { margin-top:12px; padding-top:12px; border-top:1px solid var(--line); font-size:21px; font-weight:850; }
    .account-checkout-form { display:grid; gap:15px; margin-top:20px; padding-top:18px; border-top:1px solid var(--line); }
    .account-checkout-form label { margin:0; }
    .account-credit-choice { display:flex; align-items:flex-start; gap:10px; color:var(--ink); }
    .account-credit-choice input { width:18px; min-height:18px; height:18px; margin:3px 0 0; }
    .account-credit-choice span { display:block; }
    .account-checkout-form .button { width:100%; }
    .favourite-preview-trigger { display:block; cursor:zoom-in; }
    .account-favourite-preview { width:min(1100px,calc(100% - 24px)); max-height:calc(100% - 24px); padding:0; border:0; border-radius:12px; background:#101820; color:#fff; box-shadow:0 30px 90px #0008; }
    .account-favourite-preview::backdrop { background:#000c; }
    .account-favourite-preview-inner { display:flex; max-height:calc(100vh - 24px); flex-direction:column; }
    .account-favourite-preview-bar { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:11px 16px; }
    .account-favourite-preview-bar button { border:1px solid #52636c; border-radius:7px; background:#24323a; color:#fff; cursor:pointer; padding:8px 12px; }
    .account-favourite-preview-frame { position:relative; width:fit-content; max-width:100%; margin:auto; }
    .account-favourite-preview-frame img { display:block; width:auto; max-width:100%; max-height:calc(100vh - 115px); object-fit:contain; }
    .account-favourite-preview-actions { position:absolute; right:10px; bottom:10px; display:flex; gap:7px; }
    .account-favourite-preview-actions .selection-icon { border-color:#ffffff9c; background:#102a3bd9; color:#fff; backdrop-filter:blur(4px); }
    .account-favourite-preview-actions .selection-icon.heart { background:#385fbe; }
    .account-favourite-preview-actions .selection-icon.cart.is-selected { background:#078bc4; }
    @media(max-width:600px) { .account-selection-nav { align-items:stretch; flex-direction:column; } .account-selection-links { width:100%; margin-left:0; } .account-selection-links a { flex:1; } .event-selection-item { align-items:flex-start; flex-wrap:wrap; } .event-selection-actions { margin-left:113px; } .event-selection-items.is-favourites { grid-template-columns:repeat(2,minmax(0,1fr)); } .event-selection-items.is-favourites .event-selection-actions { margin-left:0; } }
    @media(prefers-reduced-motion:reduce) { .account-selection-nav a { transition:none; } .account-selection-nav a:hover,.account-selection-nav a:focus-visible { transform:none; } }
</style>
@endpush

@section('content')
<section class="hero"><div class="container"><div class="eyebrow">Your DancePro account</div><h1>{{ $mode === 'favourites' ? 'Favourites' : 'Cart' }}</h1><p class="lead">Media saved across all your events.</p></div></section>
<section class="section"><div class="container">
    <nav class="account-selection-nav" aria-label="Saved media"><a href="{{ route('customer.media.index') }}">My Media</a><span class="account-selection-links"><a href="{{ route('customer.media.favourites') }}" @if($mode === 'favourites')aria-current="page"@endif>♡ Favourites</a><a href="{{ route('customer.media.cart') }}" @if($mode === 'cart')aria-current="page"@endif><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.1 11h11.5l2-8H6"/><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>Cart</a></span></nav>
    @if(session('status'))<p role="status">{{ session('status') }}</p>@endif
    @if($errors->any())<div class="card card-body" role="alert">{{ $errors->first() }}</div>@endif
    @forelse($groups as $group)
        <section class="card event-selection-group" @if($mode === 'cart')data-cart-group data-subtotal="{{ $group['subtotal'] }}" data-discount="{{ $group['discount'] }}" data-total="{{ $group['total'] }}"@endif>
            <header class="event-selection-head">
                @if($group['logo'])<img class="event-selection-logo" src="{{ $group['logo'] }}" alt="">@endif
                <div><h2>{{ $group['title'] }}</h2><p>{{ ucfirst($group['kind']) }} @if($group['date']) · {{ $group['date']->format('j M Y') }} @endif</p></div>
                @if($mode === 'cart')<label class="event-selection-choice"><input type="checkbox" name="cart_groups[]" form="account-cart-checkout" checked data-include-cart value="{{ $group['kind'] }}:{{ $group['event']->uuid }}"><span>Include in purchase</span></label>@endif
            </header>
            <div class="event-selection-items {{ $mode === 'favourites' ? 'is-favourites' : '' }}" data-event-items>
                @foreach($group['selections'] as $selection)
                    @if($group['kind'] === 'competition')
                        @php($detail = $group['details'][$selection->id])
                        @php($canPreviewFavourite = $mode === 'favourites' && $selection->product === 'photo' && $selection->photo_uuid)
                        <article class="event-selection-item" data-selection-item>
                            <a href="{{ $canPreviewFavourite ? route('customer.competitions.image', ['schedulingEvent' => $group['event'], 'photo' => $selection->photo_uuid]) : $detail['view_url'] }}" @if($canPreviewFavourite)class="favourite-preview-trigger" data-favourite-preview data-preview-src="{{ route('customer.competitions.image', ['schedulingEvent' => $group['event'], 'photo' => $selection->photo_uuid]) }}"@endif>@if($detail['thumbnail_url'])<img class="event-selection-thumb" src="{{ $detail['thumbnail_url'] }}" alt="" loading="lazy">@else<span class="event-selection-thumb"></span>@endif</a>
                            <div class="event-selection-copy"><span>{{ $detail['section'] }}</span><strong>{{ $detail['competitor'] }}</strong><span>{{ match($selection->product) { 'photo' => 'Individual photo', 'routine_photos' => 'All routine photos', 'routine_video' => 'Routine video', default => 'Routine package' } }} @if($selection->in_cart) · ${{ number_format(($group['lineAmounts'][$selection->id] ?? 0) / 100, 2) }} @endif</span></div>
                            <div class="event-selection-actions">
                                @if($mode === 'favourites')<form method="post" action="{{ route('customer.competitions.selections.store', $group['event']) }}">@csrf<input type="hidden" name="item_uuid" value="{{ $selection->item_uuid }}"><input type="hidden" name="photo_uuid" value="{{ $selection->photo_uuid }}"><input type="hidden" name="product" value="{{ $selection->product }}"><input type="hidden" name="action" value="{{ $selection->in_cart ? 'remove' : 'add' }}"><button class="selection-icon cart {{ $selection->in_cart ? 'is-selected' : '' }}" title="{{ $selection->in_cart ? 'Remove from cart' : 'Add to cart' }}" aria-label="{{ $selection->in_cart ? 'Remove from cart' : 'Add to cart' }}"><svg viewBox="0 0 24 24"><path d="M3 4h2l2.1 11h11.5l2-8H6"/><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg></button></form>@endif
                                <form method="post" action="{{ route('customer.competitions.selections.store', $group['event']) }}">@csrf<input type="hidden" name="item_uuid" value="{{ $selection->item_uuid }}"><input type="hidden" name="photo_uuid" value="{{ $selection->photo_uuid }}"><input type="hidden" name="product" value="{{ $selection->product }}"><input type="hidden" name="action" value="{{ $mode === 'favourites' ? 'unfavourite' : 'remove' }}"><button class="selection-icon {{ $mode === 'favourites' ? 'heart' : 'bin' }}" aria-label="Remove from {{ $mode }}"><svg viewBox="0 0 24 24">@if($mode === 'favourites')<path d="M20.8 8.5c0 5-8.8 11-8.8 11s-8.8-6-8.8-11a4.6 4.6 0 0 1 8.8-1.8 4.6 4.6 0 0 1 8.8 1.8Z"/>@else<path d="M4 7h16M9 7V4h6v3m3 0-1 13H7L6 7m4 4v6m4-6v6"/>@endif</svg></button></form>
                            </div>
                        </article>
                    @else
                        @php($asset = $selection->asset)
                        <article class="event-selection-item" data-selection-item>
                            <a href="{{ $mode === 'favourites' ? route('customer.concerts.photos.image', ['concert' => $selection->concert, 'asset' => $asset]) : route('customer.concerts.photos.show', ['concert' => $selection->concert, 'asset' => $asset->uuid]) }}" @if($mode === 'favourites')class="favourite-preview-trigger" data-favourite-preview data-preview-src="{{ route('customer.concerts.photos.image', ['concert' => $selection->concert, 'asset' => $asset]) }}"@endif><img class="event-selection-thumb" src="{{ route('customer.concerts.photos.image', ['concert' => $selection->concert, 'asset' => $asset]) }}" alt="" loading="lazy"></a>
                            <div class="event-selection-copy"><strong>{{ $asset->collection->name }}</strong><span>Individual concert photo @if($selection->in_cart) · ${{ number_format(($group['lineAmounts'][$selection->id] ?? 0) / 100, 2) }} @endif</span></div>
                            <div class="event-selection-actions">
                                @if($mode === 'favourites')<form method="post" action="{{ route('customer.concerts.photos.selections.store', $selection->concert) }}">@csrf<input type="hidden" name="asset_uuid" value="{{ $asset->uuid }}"><input type="hidden" name="action" value="{{ $selection->in_cart ? 'remove' : 'add' }}"><button class="selection-icon cart {{ $selection->in_cart ? 'is-selected' : '' }}" title="{{ $selection->in_cart ? 'Remove from cart' : 'Add to cart' }}" aria-label="{{ $selection->in_cart ? 'Remove from cart' : 'Add to cart' }}"><svg viewBox="0 0 24 24"><path d="M3 4h2l2.1 11h11.5l2-8H6"/><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg></button></form>@endif
                                <form method="post" action="{{ route('customer.concerts.photos.selections.store', $selection->concert) }}">@csrf<input type="hidden" name="asset_uuid" value="{{ $asset->uuid }}"><input type="hidden" name="action" value="{{ $mode === 'favourites' ? 'unfavourite' : 'remove' }}"><button class="selection-icon {{ $mode === 'favourites' ? 'heart' : 'bin' }}" aria-label="Remove from {{ $mode }}"><svg viewBox="0 0 24 24">@if($mode === 'favourites')<path d="M20.8 8.5c0 5-8.8 11-8.8 11s-8.8-6-8.8-11a4.6 4.6 0 0 1 8.8-1.8 4.6 4.6 0 0 1 8.8 1.8Z"/>@else<path d="M4 7h16M9 7V4h6v3m3 0-1 13H7L6 7m4 4v6m4-6v6"/>@endif</svg></button></form>
                            </div>
                        </article>
                    @endif
                @endforeach
            </div>
            @if($group['selections']->count() > 4)<div class="event-selection-more"><button type="button" data-view-more aria-expanded="false">View more ↓</button></div>@endif
            @if($mode === 'cart')<footer class="event-selection-totals"><p><span>Event subtotal</span><strong>${{ number_format($group['subtotal'] / 100, 2) }}</strong></p>@if($group['discount'] > 0)<p><span>Event bulk discount ({{ $group['discount_percent'] }}%)</span><strong>−${{ number_format($group['discount'] / 100, 2) }}</strong></p>@endif<p><span>Event total</span><strong>${{ number_format($group['total'] / 100, 2) }}</strong></p></footer>@endif
        </section>
    @empty
        <div class="empty"><p>{{ $mode === 'cart' ? 'Your cart is empty.' : 'Your favourites are empty.' }}</p><a href="{{ route('customer.media.index') }}">Browse My Media</a></div>
    @endforelse
    @if($mode === 'cart' && $groups->isNotEmpty())<aside class="card account-selection-total"><p><span>Selected subtotal</span><strong data-selected-subtotal>${{ number_format($subtotal / 100, 2) }}</strong></p><p><span>Selected event discounts</span><strong data-selected-discount>−${{ number_format($discount / 100, 2) }}</strong></p><p class="total"><span>Selected total</span><strong data-selected-total>${{ number_format($total / 100, 2) }}</strong></p><p class="meta" data-selected-summary>All {{ $groups->count() }} event carts selected. Untick an event above to save that cart for later.</p><p class="meta">Each event keeps its own pricing and bulk discount. A discount code and account credit are applied when the order is reviewed.</p>
        <form id="account-cart-checkout" class="account-checkout-form" method="post" action="{{ route('customer.checkout.account-cart') }}">@csrf
            <label>Discount code<input name="discount_code" value="{{ old('discount_code') }}" maxlength="32" autocomplete="off"><span class="meta">Optional. Codes apply once across the selected event carts.</span></label>
            @if($creditBalance > 0)<label class="account-credit-choice"><input type="checkbox" name="use_account_credit" value="1" @checked(old('use_account_credit'))><span>Use my ${{ number_format($creditBalance / 100, 2) }} account credit<span class="meta">Applied after discounts. GST is calculated on the remaining paid amount.</span></span></label>@endif
            <button class="button" type="submit" data-account-checkout>Proceed to checkout</button>
        </form>
    </aside>@endif
    @if($mode === 'favourites' && $groups->isNotEmpty())
        <dialog class="account-favourite-preview" data-favourite-preview-dialog aria-label="Favourite photo preview"><div class="account-favourite-preview-inner"><div class="account-favourite-preview-bar"><strong>Photo preview</strong><button type="button" data-preview-close>Close ✕</button></div><div class="account-favourite-preview-frame"><img data-preview-image alt="Favourite photo preview"><div class="account-favourite-preview-actions"><button class="selection-icon heart" type="button" data-preview-favourite aria-label="Remove from favourites"><svg viewBox="0 0 24 24"><path d="M20.8 8.5c0 5-8.8 11-8.8 11s-8.8-6-8.8-11a4.6 4.6 0 0 1 8.8-1.8 4.6 4.6 0 0 1 8.8 1.8Z"/></svg></button><button class="selection-icon cart" type="button" data-preview-cart aria-label="Add to cart"><svg viewBox="0 0 24 24"><path d="M3 4h2l2.1 11h11.5l2-8H6"/><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg></button></div></div></div></dialog>
    @endif
</div></section>
@endsection

@push('scripts')
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
const favouritePreview = document.querySelector('[data-favourite-preview-dialog]');
if (favouritePreview) {
    let activeFavouriteCard = null;
    document.querySelectorAll('[data-favourite-preview]').forEach(link => link.addEventListener('click', event => {
        event.preventDefault();
        activeFavouriteCard = link.closest('[data-selection-item]');
        favouritePreview.querySelector('[data-preview-image]').src = link.dataset.previewSrc;
        const cartButton = activeFavouriteCard.querySelector('.selection-icon.cart');
        favouritePreview.querySelector('[data-preview-cart]').classList.toggle('is-selected', cartButton?.classList.contains('is-selected'));
        favouritePreview.querySelector('[data-preview-cart]').setAttribute('aria-label', cartButton?.classList.contains('is-selected') ? 'Remove from cart' : 'Add to cart');
        favouritePreview.showModal();
    }));
    favouritePreview.querySelector('[data-preview-close]').addEventListener('click', () => favouritePreview.close());
    favouritePreview.querySelector('[data-preview-favourite]').addEventListener('click', () => activeFavouriteCard?.querySelector('.selection-icon.heart')?.click());
    favouritePreview.querySelector('[data-preview-cart]').addEventListener('click', () => activeFavouriteCard?.querySelector('.selection-icon.cart')?.click());
    favouritePreview.addEventListener('click', event => { if (event.target === favouritePreview) favouritePreview.close(); });
    favouritePreview.addEventListener('close', () => favouritePreview.querySelector('[data-preview-image]').removeAttribute('src'));
}
document.querySelectorAll('[data-event-items]').forEach(group => {
    const items = [...group.querySelectorAll('[data-selection-item]')];
    const button = group.nextElementSibling?.querySelector('[data-view-more]');
    if (!button) return;
    let expanded = false;
    const render = () => items.forEach((item, index) => { item.hidden = !expanded && index >= 4; });
    button.addEventListener('click', () => { expanded = !expanded; button.setAttribute('aria-expanded', String(expanded)); button.textContent = expanded ? 'View less ↑' : 'View more ↓'; render(); });
    render();
});
const cartGroups = [...document.querySelectorAll('[data-cart-group]')];
const cartChoiceKey = @json('dancepro-cart-groups-'.auth()->user()->uuid);
let cartChoices = {};
try { cartChoices = JSON.parse(sessionStorage.getItem(cartChoiceKey) || '{}'); } catch (_) {}
const money = cents => new Intl.NumberFormat('en-AU', {style:'currency', currency:'AUD'}).format(cents / 100);
const updateSelectedCartTotal = () => {
    if (!cartGroups.length) return;
    const selected = cartGroups.filter(group => group.querySelector('[data-include-cart]').checked);
    const sum = key => selected.reduce((total, group) => total + Number(group.dataset[key] || 0), 0);
    document.querySelector('[data-selected-subtotal]').textContent = money(sum('subtotal'));
    document.querySelector('[data-selected-discount]').textContent = `−${money(sum('discount'))}`;
    document.querySelector('[data-selected-total]').textContent = money(sum('total'));
    document.querySelector('[data-selected-summary]').textContent = selected.length
        ? `${selected.length} of ${cartGroups.length} event carts selected. Unselected carts remain saved for later.`
        : 'No event carts selected. Your media remains saved for later.';
    document.querySelector('[data-account-checkout]').disabled = selected.length === 0;
};
cartGroups.forEach(group => {
    const input = group.querySelector('[data-include-cart]');
    if (cartChoices[input.value] === false) input.checked = false;
    const renderChoice = () => {
        group.classList.toggle('is-deferred', !input.checked);
        input.nextElementSibling.textContent = input.checked ? 'Include in purchase' : 'Saved for later';
    };
    input.addEventListener('change', () => {
        cartChoices[input.value] = input.checked;
        sessionStorage.setItem(cartChoiceKey, JSON.stringify(cartChoices));
        renderChoice();
        updateSelectedCartTotal();
    });
    renderChoice();
});
updateSelectedCartTotal();
</script>
@endpush
