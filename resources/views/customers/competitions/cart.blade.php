@extends('layouts.public', ['title' => ($activeTab === 'favourites' ? 'Favourites' : 'Cart').' · '.$event->name, 'footerLabel' => 'Competition media'])

@push('styles')
<style>
    .selection-list { display:grid; gap:12px; max-width:840px; }
    .selection-list.is-favourites { grid-template-columns:repeat(auto-fill,minmax(190px,1fr)); max-width:none; align-items:start; }
    .selection-row { display:flex; align-items:center; gap:15px; padding:13px; overflow:visible; }
    .selection-row.is-favourite { display:flex; flex-direction:column; align-items:stretch; gap:10px; padding:0; overflow:visible; }
    .selection-image { display:block; flex:0 0 106px; width:106px; height:80px; border-radius:7px; background:#15212a; object-fit:contain; }
    .selection-row.is-favourite .selection-image { width:100%; height:auto; flex:auto; border-radius:8px 8px 0 0; }
    .selection-row.is-favourite .selection-image-fallback { aspect-ratio:3/2; }
    .selection-thumbnail-link { display:block; width:100%; border-radius:8px 8px 0 0; text-decoration:none; }
    .selection-thumbnail-link:hover, .selection-thumbnail-link:focus-visible { outline:3px solid #078bc4; outline-offset:2px; }
    .selection-row:not(.is-favourite) .selection-thumbnail-link { flex:0 0 106px; width:106px; border-radius:7px; }
    .selection-row.is-favourite .selection-info { padding:0 12px; }
    .selection-image-fallback { display:grid; place-items:center; color:#c8dbe4; font-size:30px; }
    .selection-info { min-width:0; flex:1; }
    .selection-info p { margin:2px 0 0; color:var(--muted); line-height:1.35; }
    .selection-info strong { display:block; margin-top:3px; }
    .selection-actions { display:flex; align-items:center; flex-wrap:wrap; gap:7px; }
    .selection-row.is-favourite .selection-actions { margin-top:auto; padding:0 12px 12px; }
    .selection-actions form { margin:0; }
    .selection-actions .button, .selection-actions button { display:inline-flex; align-items:center; min-height:37px; border-radius:7px; padding:7px 11px; cursor:pointer; font:inherit; font-weight:700; font-size:13px; text-decoration:none; }
    .selection-actions button { border:1px solid #cbd9df; background:#fff; color:#183441; }
    .selection-actions button.add-cart { border-color:#87bfd7; background:#fff; color:#057eb2; }
    .selection-actions button:disabled { cursor:default; opacity:.65; }
    .selection-actions .selection-icon-button { position:relative; display:inline-grid; width:38px; height:38px; min-height:38px; place-items:center; padding:0; border:1px solid #cbd9df; border-radius:8px; background:#fff; color:#285068; }
    .selection-actions .selection-icon-button svg { width:20px; height:20px; fill:none; stroke:currentColor; stroke-width:1.8; stroke-linecap:round; stroke-linejoin:round; }
    .selection-actions .selection-icon-button:hover, .selection-actions .selection-icon-button:focus-visible { border-color:#087bab; background:#e8f7fd; color:#006b98; }
    .selection-row.is-favourite .selection-icon-button.add-cart { border-color:#87bfd7; background:#fff; color:#057eb2; }
    .selection-row.is-favourite .selection-icon-button.add-cart.is-in-cart { border-color:#078bc4; background:#078bc4; color:#fff; }
    .selection-actions .selection-icon-button.is-favourite { border-color:#a8bde7; background:#edf2ff; color:#335fc7; }
    .selection-actions .selection-icon-button.is-favourite svg { fill:currentColor; }
    .selection-actions .selection-icon-button.is-remove { border-color:#e6b5bb; background:#fff3f4; color:#ad1d2c; }
    .selection-actions .selection-icon-button.is-remove:hover, .selection-actions .selection-icon-button.is-remove:focus-visible { background:#fde3e6; }
    .selection-actions .selection-icon-button[data-tooltip]:hover::after, .selection-actions .selection-icon-button[data-tooltip]:focus-visible::after { position:absolute; z-index:3; left:50%; bottom:calc(100% + 7px); transform:translateX(-50%); padding:6px 9px; border-radius:6px; background:#10212c; color:#fff; content:attr(data-tooltip); font-size:12px; font-weight:700; white-space:nowrap; box-shadow:0 4px 13px #0004; }
    .selection-action-error { color:#ad1d2c; font-size:12px; font-weight:700; }
    .selection-action-error[hidden] { display:none; }
    .selection-preview { position:fixed; inset:0; width:min(1100px,calc(100% - 24px)); max-height:calc(100% - 24px); padding:0; border:0; border-radius:12px; background:#101820; color:#fff; box-shadow:0 30px 90px #0008; }
    .selection-preview::backdrop { background:#000c; }
    .selection-preview-bar { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:12px 16px; }
    .selection-preview-bar p { margin:0; font-weight:750; }
    .selection-preview-close { border:1px solid #52636c; border-radius:7px; background:#24323a; color:#fff; cursor:pointer; font:inherit; font-weight:750; padding:8px 12px; }
    .selection-preview-image { display:block; max-width:100%; max-height:calc(100vh - 95px); width:auto; height:auto; margin:auto; object-fit:contain; }
    .selection-preview-frame { position:relative; width:fit-content; max-width:100%; margin:auto; }
    .selection-preview-actions { position:absolute; right:10px; bottom:10px; display:flex; gap:7px; }
    .selection-preview-actions form[data-selection-kind="favourite"] { order:1; }
    .selection-preview-actions form[data-selection-kind="cart"] { order:2; }
    .selection-preview-actions form { margin:0; }
    .selection-preview-actions .selection-icon-button { position:relative; display:grid; width:40px; height:38px; place-items:center; border:1px solid #ffffff9c; border-radius:8px; background:#102a3bd9; color:#fff; cursor:pointer; backdrop-filter:blur(4px); }
    .selection-preview-actions .selection-icon-button svg { width:20px; height:20px; fill:none; stroke:currentColor; stroke-width:1.8; stroke-linecap:round; stroke-linejoin:round; }
    .selection-preview-actions .selection-icon-button.is-in-cart { border-color:#79d4f6; background:#078bc4; color:#fff; }
    .selection-preview-actions .selection-icon-button.is-favourite { background:#385fbe; }
    .selection-preview-actions .selection-icon-button.is-favourite svg { fill:currentColor; }
    .selection-preview-actions .selection-icon-button:disabled { opacity:.55; }
    .selection-preview-actions .selection-icon-button[data-tooltip]:hover::after, .selection-preview-actions .selection-icon-button[data-tooltip]:focus-visible::after { position:absolute; right:0; bottom:calc(100% + 7px); padding:6px 9px; border-radius:6px; background:#10212c; color:#fff; content:attr(data-tooltip); font-size:12px; font-weight:700; white-space:nowrap; }
    .selection-preview-error { margin:8px 16px 16px; color:#ffd4d8; font-weight:700; }
    .selection-preview-error[hidden] { display:none; }
    .cart-totals { max-width:760px; padding:20px; margin-top:20px; }
    .cart-totals p { display:flex; justify-content:space-between; gap:12px; margin:7px 0; }
    .cart-totals .total { border-top:1px solid #cad7dd; padding-top:12px; font-size:20px; font-weight:800; }
</style>
@endpush

@section('content')
@php
    $competitionRoutes = $competitionRoutes ?? [
        'show' => 'customer.competitions.show', 'image' => 'customer.competitions.image',
        'selection' => 'customer.competitions.selections.store',
    ];
    $mediaPricing = app(\App\Features\Media\Services\MediaCommerceSettings::class);
@endphp
<div class="container" style="padding-top:18px"><a href="{{ route($competitionRoutes['show'], $event) }}">← {{ $event->name }}</a></div>
<section class="hero" style="padding:36px 0"><div class="container"><h1>{{ $activeTab === 'favourites' ? 'Favourites' : 'Cart' }}</h1><p class="lead">{{ $event->name }}</p></div></section>
<section class="section"><div class="container">
    @include('customers.competitions.partials.tabs')
    @if(session('status'))<p role="status">{{ session('status') }}</p>@endif
    @if($errors->any())<div class="card card-body" role="alert">{{ $errors->first() }}</div>@endif
    @if($activeTab === 'favourites')
    <h2>Saved favourites</h2>
    <div class="selection-list is-favourites">
        @forelse($selections->where('is_favourite', true) as $selection)
            @php($detail = $selectionDetails[$selection->id])
            <div class="card selection-row is-favourite">
                <a class="selection-thumbnail-link" href="{{ $detail['view_url'] }}" @if($selection->product === 'photo' && $detail['can_add'])data-selection-preview="{{ route($competitionRoutes['image'], ['schedulingEvent' => $event, 'photo' => $selection->photo_uuid]) }}" data-item-uuid="{{ $selection->item_uuid }}" data-photo-uuid="{{ $selection->photo_uuid }}" data-cart="{{ $selection->in_cart ? 'true' : 'false' }}" data-favourite="true"@endif aria-label="{{ $selection->product === 'photo' ? 'View photo of' : 'View album for' }} {{ $detail['competitor'] }}">
                    @if($detail['thumbnail_url'])<img class="selection-image" src="{{ $detail['thumbnail_url'] }}" alt="" loading="lazy">
                    @else<span class="selection-image selection-image-fallback" aria-hidden="true">▣</span>@endif
                </a>
                <div class="selection-info">
                    <p>{{ $detail['section'] }}</p>
                    <strong>{{ $detail['competitor'] }}</strong>
                    <p>{{ match($selection->product) { 'photo' => 'Photo', 'routine_video' => 'Video', 'routine_package' => 'Package', default => 'Album photos' } }}</p>
                </div>
                <div class="selection-actions">
                    <form method="post" action="{{ route($competitionRoutes['selection'], $event) }}" data-selection-form data-selection-kind="cart" data-card-form data-can-add="{{ $detail['can_add'] ? 'true' : 'false' }}">@csrf
                        <input type="hidden" name="item_uuid" value="{{ $selection->item_uuid }}"><input type="hidden" name="photo_uuid" value="{{ $selection->photo_uuid }}">
                        <input type="hidden" name="product" value="{{ $selection->product }}"><input type="hidden" name="action" value="add">
                        <button class="selection-icon-button add-cart {{ $selection->in_cart ? 'is-in-cart' : '' }}" type="submit" data-tooltip="{{ $selection->in_cart ? 'In cart' : ($detail['can_add'] ? 'Add to cart' : 'Unavailable') }}" aria-label="{{ $selection->in_cart ? 'Already in cart' : ($detail['can_add'] ? 'Add to cart' : 'Unavailable to add to cart') }}" @disabled($selection->in_cart || !$detail['can_add'])><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.1 11h11.5l2-8H6"/><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg></button>
                    </form>
                    <span class="selection-action-error" role="alert" hidden></span>
                    <form method="post" action="{{ route($competitionRoutes['selection'], $event) }}" data-selection-form data-selection-kind="favourite" data-card-form>@csrf
                        <input type="hidden" name="item_uuid" value="{{ $selection->item_uuid }}"><input type="hidden" name="photo_uuid" value="{{ $selection->photo_uuid }}">
                        <input type="hidden" name="product" value="{{ $selection->product }}"><input type="hidden" name="action" value="unfavourite">
                        <button class="selection-icon-button is-favourite" type="submit" data-tooltip="Remove from favourites" aria-label="Remove from favourites"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.8 8.5c0 5-8.8 11-8.8 11s-8.8-6-8.8-11a4.6 4.6 0 0 1 8.8-1.8 4.6 4.6 0 0 1 8.8 1.8Z"/></svg></button>
                    </form>
                </div>
            </div>
        @empty<p>No favourites saved for this event yet.</p>@endforelse
    </div>
    @else
    <h2>Your cart</h2>
    <div class="selection-list">
        @forelse($cart as $selection)
            @php($detail = $selectionDetails[$selection->id])
            <div class="card selection-row">
                <a class="selection-thumbnail-link" href="{{ $detail['view_url'] }}" @if($selection->product === 'photo' && $detail['can_add'])data-selection-preview="{{ route($competitionRoutes['image'], ['schedulingEvent' => $event, 'photo' => $selection->photo_uuid]) }}" data-item-uuid="{{ $selection->item_uuid }}" data-photo-uuid="{{ $selection->photo_uuid }}" data-cart="true" data-favourite="{{ $selection->is_favourite ? 'true' : 'false' }}"@endif aria-label="{{ $selection->product === 'photo' ? 'View photo of' : 'View album for' }} {{ $detail['competitor'] }}">
                    @if($detail['thumbnail_url'])<img class="selection-image" src="{{ $detail['thumbnail_url'] }}" alt="" loading="lazy">
                    @else<span class="selection-image selection-image-fallback" aria-hidden="true">▣</span>@endif
                </a>
                <div class="selection-info">
                <p>{{ $detail['section'] }}</p><strong>{{ $detail['competitor'] }}</strong>
                <p>{{ match($selection->product) { 'photo' => 'Individual photo', 'routine_photos' => 'All routine photos', 'routine_video' => 'Routine video', default => 'Routine package' } }} · ${{ number_format(($lineAmounts[$selection->id] ?? $mediaPricing->price('competition_'.$selection->product)) / 100, 2) }}
                    @if(!$priced->contains('id', $selection->id)) · Included in another cart item
                    @elseif($selection->product === 'routine_package' && ($lineAmounts[$selection->id] ?? null) !== $mediaPricing->price('competition_routine_package')) · Previously purchased video credit
                    @endif</p></div>
                <div class="selection-actions">
                <form method="post" action="{{ route($competitionRoutes['selection'], $event) }}">@csrf
                    <input type="hidden" name="item_uuid" value="{{ $selection->item_uuid }}"><input type="hidden" name="photo_uuid" value="{{ $selection->photo_uuid }}">
                    <input type="hidden" name="product" value="{{ $selection->product }}"><input type="hidden" name="action" value="remove">
                    <button class="selection-icon-button is-remove" type="submit" data-tooltip="Remove from cart" aria-label="Remove from cart"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3m3 0-1 13H7L6 7m4 4v6m4-6v6"/></svg></button>
                </form>
                </div>
            </div>
        @empty<p>Your cart is empty.</p>@endforelse
    </div>
    @if($cart->isNotEmpty())
        <div class="card cart-totals">
            <p><span>Subtotal</span><strong>${{ number_format($subtotal / 100, 2) }}</strong></p>
            <p><span>Bulk discount ({{ $discount_percent }}%)</span><strong>−${{ number_format($discount / 100, 2) }}</strong></p>
            <p class="total"><span>Total including GST</span><span>${{ number_format($total / 100, 2) }}</span></p>
            @foreach($notices as $notice)<p>{{ $notice }}</p>@endforeach
            <label style="display:block;margin-top:16px">Discount code<input name="discount_code" form="competition-checkout-form" value="{{ old('discount_code', session('checkout.discount_code')) }}" maxlength="32" autocomplete="off" style="display:block;margin-top:6px"><span class="meta">A code reduces the amount used to calculate the bulk discount.</span></label>
            @if(!($guestCheckout ?? false) && $creditBalance > 0)<label style="display:flex;align-items:flex-start;gap:9px;margin-top:16px"><input type="checkbox" name="use_account_credit" value="1" form="competition-checkout-form" @checked(old('use_account_credit')) style="width:auto;min-height:0;margin-top:4px"><span>Use my ${{ number_format($creditBalance / 100, 2) }} account credit<span class="meta" style="display:block">Credit is applied after discounts. GST is calculated on the remaining total.</span></span></label>@endif
            @if($guestCheckout ?? false)
                <form id="competition-checkout-form" method="get" action="{{ route($competitionRoutes['checkout'], $event) }}" style="display:block;margin-top:16px"><button class="button" type="submit">Create an account to continue to checkout</button> <button class="button secondary" type="submit" name="account" value="login">Sign in</button></form>
            @else
                <form id="competition-checkout-form" method="post" action="{{ route('customer.competitions.checkout', $event) }}" style="display:block;margin-top:16px">@csrf<button class="button" type="submit">Review order</button></form>
            @endif
        </div>
    @endif
    @endif
    <dialog class="selection-preview" id="selection-photo-viewer" aria-label="Photo preview">
        <div class="selection-preview-bar"><p>Watermarked preview</p><button class="selection-preview-close" type="button" data-close>Close ✕</button></div>
        <div class="selection-preview-frame">
            <img class="selection-preview-image" alt="Watermarked photo preview">
            <div class="selection-preview-actions">
                <form method="post" action="{{ route($competitionRoutes['selection'], $event) }}" data-selection-form data-selection-kind="cart" data-preview-form>@csrf
                    <input type="hidden" name="item_uuid"><input type="hidden" name="photo_uuid"><input type="hidden" name="product" value="photo"><input type="hidden" name="action" value="add">
                    <button class="selection-icon-button" type="submit" aria-label="Add this photo to cart"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.1 11h11.5l2-8H6"/><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg></button>
                </form>
                <form method="post" action="{{ route($competitionRoutes['selection'], $event) }}" data-selection-form data-selection-kind="favourite" data-preview-form>@csrf
                    <input type="hidden" name="item_uuid"><input type="hidden" name="photo_uuid"><input type="hidden" name="product" value="photo"><input type="hidden" name="action" value="favourite">
                    <button class="selection-icon-button" type="submit" aria-label="Add this photo to favourites"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.8 8.5c0 5-8.8 11-8.8 11s-8.8-6-8.8-11a4.6 4.6 0 0 1 8.8-1.8 4.6 4.6 0 0 1 8.8 1.8Z"/></svg></button>
                </form>
            </div>
        </div>
        <p class="selection-preview-error" role="alert" hidden></p>
    </dialog>
</div></section>
@endsection

@push('scripts')
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
(() => {
    const viewer = document.getElementById('selection-photo-viewer');
    const forms = [...document.querySelectorAll('[data-selection-form]')];
    let opener = null;
    const updateBadge = (selector, count) => {
        const tab = document.querySelector(selector);
        if (!tab) return;
        let badge = tab.querySelector('.competition-tab-count');
        if (count > 0) {
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'competition-tab-count';
                tab.append(badge);
            }
            badge.textContent = count;
        } else badge?.remove();
    };
    const updateButton = (form, active) => {
        const button = form.querySelector('button');
        const subject = form.elements.product.value === 'photo' ? 'this photo' : 'this product';
        if (form.dataset.selectionKind === 'cart') {
            form.elements.action.value = 'add';
            button.disabled = active || form.dataset.canAdd === 'false';
            button.classList.toggle('is-in-cart', active);
            button.dataset.tooltip = active ? 'In cart' : 'Add to cart';
            button.setAttribute('aria-label', active ? 'Already in cart' : `Add ${subject} to cart`);
        } else {
            form.elements.action.value = active ? 'unfavourite' : 'favourite';
            button.disabled = false;
            button.classList.toggle('is-favourite', active);
            button.dataset.tooltip = active ? 'Remove from favourites' : 'Add to favourites';
            button.setAttribute('aria-label', active ? `Remove ${subject} from favourites` : `Add ${subject} to favourites`);
        }
    };
    const updateEmpty = () => {
        const grid = document.querySelector('.selection-list.is-favourites');
        if (grid && !grid.querySelector('.selection-row')) grid.innerHTML = '<p>No favourites saved for this event yet.</p>';
    };
    const sync = data => {
        document.querySelectorAll('[data-selection-preview]').forEach(link => {
            if (link.dataset.photoUuid !== data.photo_uuid) return;
            link.dataset.cart = data.in_cart ? 'true' : 'false';
            link.dataset.favourite = data.is_favourite ? 'true' : 'false';
        });
        forms.forEach(form => {
            if (form.elements.product.value !== data.product || form.elements.item_uuid.value !== data.item_uuid
                || (form.elements.photo_uuid.value || null) !== data.photo_uuid) return;
            updateButton(form, form.dataset.selectionKind === 'cart' ? data.in_cart : data.is_favourite);
        });
        updateBadge('[data-cart-tab]', data.cart_count);
        updateBadge('[data-favourites-tab]', data.favourite_count);
    };
    if (viewer && typeof viewer.showModal === 'function') {
        const image = viewer.querySelector('.selection-preview-image');
        document.querySelectorAll('[data-selection-preview]').forEach(link => link.addEventListener('click', event => {
            event.preventDefault();
            opener = link;
            image.src = link.dataset.selectionPreview;
            viewer.querySelectorAll('[data-preview-form]').forEach(form => {
                form.elements.item_uuid.value = link.dataset.itemUuid;
                form.elements.photo_uuid.value = link.dataset.photoUuid;
                updateButton(form, form.dataset.selectionKind === 'cart'
                    ? link.dataset.cart === 'true' : link.dataset.favourite === 'true');
            });
            viewer.querySelector('.selection-preview-error').hidden = true;
            viewer.showModal();
        }));
        viewer.querySelector('[data-close]').addEventListener('click', () => viewer.close());
        viewer.addEventListener('click', event => { if (event.target === viewer) viewer.close(); });
        viewer.addEventListener('close', () => {
            image.removeAttribute('src');
            const tile = opener?.closest('.selection-row.is-favourite');
            if (tile && opener.dataset.favourite === 'false') {
                tile.remove();
                updateEmpty();
            } else opener?.focus();
        });
    }
    forms.forEach(form => form.addEventListener('submit', async event => {
        event.preventDefault();
        const button = form.querySelector('button');
        const error = form.hasAttribute('data-preview-form')
            ? viewer.querySelector('.selection-preview-error') : form.parentElement.querySelector('.selection-action-error');
        if (button.disabled) return;
        button.disabled = true;
        error.hidden = true;
        try {
            const response = await fetch(form.getAttribute('action'), {
                method: 'POST', body: new FormData(form), credentials: 'same-origin',
                headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
            });
            const result = await response.json().catch(() => null);
            if (!response.ok || !result?.success) throw new Error(result?.message || 'Could not save your choice. Please refresh and try again.');
            sync(result.data);
            if (form.hasAttribute('data-card-form') && form.dataset.selectionKind === 'favourite'
                && !result.data.is_favourite) {
                form.closest('.selection-row').remove();
                updateEmpty();
            }
        } catch (cause) {
            button.disabled = false;
            error.textContent = cause.message || 'Could not save your choice.';
            error.hidden = false;
        }
    }));
})();
</script>
@endpush
