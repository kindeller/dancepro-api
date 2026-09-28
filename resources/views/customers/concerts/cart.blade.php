@extends('layouts.public', ['title' => $studio->name.' photo cart', 'footerLabel' => 'Concert photos'])

@push('styles')
<style>
    .concert-shop-tabs { display:flex; width:fit-content; max-width:100%; overflow-x:auto; margin:0 0 28px; border-bottom:2px solid #cddbe1; }
    .concert-shop-tabs a { flex:0 0 auto; padding:11px 20px; border-radius:9px 9px 0 0; color:#38505d; font-weight:800; text-decoration:none; }
    .concert-shop-tabs a[aria-current="page"] { margin-bottom:-2px; border:2px solid #cddbe1; border-bottom-color:#fff; background:#fff; color:#087eae; }
    .concert-tab-count { display:inline-grid; min-width:20px; height:20px; margin-left:5px; place-items:center; border-radius:10px; background:#087eae; color:#fff; font-size:11px; }
    .studio-cart-list { display:grid; gap:10px; max-width:800px; }
    .studio-cart-row { display:flex; align-items:center; gap:12px; padding:12px; }
    .studio-cart-row img { width:94px; max-height:94px; object-fit:contain; background:#14212a; }
    .studio-cart-row .details { flex:1; }
    .studio-cart-row p { margin:3px 0; }
    .concert-favourites { display:grid; grid-template-columns:repeat(auto-fill,minmax(190px,230px)); gap:14px; align-items:start; }
    .concert-favourite { overflow:visible; }
    .concert-favourite button[data-preview] { display:block; width:100%; aspect-ratio:4/3; overflow:hidden; border:0; border-radius:9px 9px 0 0; background:#14212a; padding:0; cursor:zoom-in; }
    .concert-favourite button[data-preview] img { width:100%; height:100%; object-fit:contain; }
    .concert-favourite-copy { padding:13px; }
    .concert-favourite-copy span { display:block; margin-top:4px; color:var(--muted); font-size:13px; }
    .concert-actions { display:flex; gap:8px; margin-top:12px; }
    .concert-icon-button { position:relative; display:grid; width:40px; height:38px; place-items:center; border:1px solid #c9dbe4; border-radius:8px; background:#fff; color:#285068; cursor:pointer; }
    .concert-icon-button.is-cart { border-color:#79d4f6; background:#078bc4; color:#fff; }
    .concert-icon-button.is-favourite { border-color:#a9bef8; background:#edf2ff; color:#385fbe; }
    .concert-icon-button.is-remove { border-color:#efb1b8; background:#fff0f1; color:#b62333; }
    .concert-icon-button svg { width:20px; height:20px; fill:none; stroke:currentColor; stroke-width:1.9; stroke-linecap:round; stroke-linejoin:round; }
    .concert-icon-button.is-favourite svg { fill:currentColor; }
    .concert-icon-button[data-tooltip]:hover::after,.concert-icon-button[data-tooltip]:focus-visible::after { position:absolute; z-index:20; right:0; bottom:calc(100% + 7px); padding:7px 9px; border-radius:6px; background:#10212c; color:#fff; content:attr(data-tooltip); font-size:12px; font-weight:700; white-space:nowrap; box-shadow:0 4px 13px #0004; }
    .studio-cart-row button[data-preview] { width:94px; height:82px; flex:0 0 auto; overflow:hidden; border:0; border-radius:7px; background:#14212a; padding:0; cursor:zoom-in; }
    .studio-cart-row button[data-preview] img { width:100%; height:100%; object-fit:contain; }
    .studio-cart-total { max-width:800px; padding:20px; margin-top:20px; }
    .studio-cart-total p { display:flex; justify-content:space-between; gap:12px; margin:7px 0; }
    .studio-cart-total .total { border-top:1px solid #ccd9df; padding-top:12px; font-size:20px; font-weight:800; }
    .concert-viewer { width:min(1100px,calc(100% - 24px)); max-height:calc(100% - 24px); padding:0; border:0; border-radius:12px; background:#101820; color:#fff; }
    .concert-viewer::backdrop { background:#000c; }
    .concert-viewer-header { display:flex; justify-content:space-between; align-items:center; padding:10px 16px; }
    .concert-viewer-header button { border:1px solid #5b6971; border-radius:7px; background:#253842; color:#fff; cursor:pointer; padding:8px 13px; }
    .concert-viewer-frame { position:relative; width:fit-content; max-width:100%; margin:auto; }
    .concert-viewer img { display:block; max-width:100%; max-height:calc(100vh - 90px); margin:auto; object-fit:contain; }
    .concert-viewer-actions { position:absolute; right:10px; bottom:10px; display:flex; gap:7px; }
    .concert-viewer-actions form[data-kind="favourite"] { order:1; }
    .concert-viewer-actions form[data-kind="cart"] { order:2; }
    .concert-feedback { position:fixed; z-index:1001; right:20px; bottom:20px; padding:12px 16px; border-radius:9px; background:#10212c; color:#fff; box-shadow:0 8px 30px #0005; font-weight:700; }
    .concert-feedback[hidden] { display:none; }
    @media(max-width:600px) { .concert-favourites { grid-template-columns:repeat(2,minmax(0,1fr)); gap:9px; } }
</style>
@endpush

@section('content')
@php
    $concertRoutes = $concertRoutes ?? ['image' => 'customer.concerts.photos.image', 'selection' => 'customer.concerts.photos.selections.store'];
    $concertBackUrl = $concertBackUrl ?? route('customer.profile.studio', $studio);
    $favourites = $selections->where('is_favourite', true);
@endphp
<svg aria-hidden="true" style="position:absolute;width:0;height:0;overflow:hidden"><symbol id="concert-cart-icon" viewBox="0 0 24 24"><path d="M3 4h2l2.1 11h11.5l2-8H6"/><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></symbol><symbol id="concert-heart-icon" viewBox="0 0 24 24"><path d="M20.8 8.5c0 5-8.8 11-8.8 11s-8.8-6-8.8-11a4.6 4.6 0 0 1 8.8-1.8 4.6 4.6 0 0 1 8.8 1.8Z"/></symbol><symbol id="concert-bin-icon" viewBox="0 0 24 24"><path d="M4 7h16M9 7V4h6v3m3 0-1 13H7L6 7m4 4v5m4-5v5"/></symbol></svg>
<div class="container" style="padding-top:18px"><a href="{{ $concertBackUrl }}">← {{ $studio->name }} concerts</a></div>
<section class="hero" style="padding:38px 0"><div class="container"><div class="eyebrow">Concert photos</div><h1>{{ $activeTab === 'cart' ? 'Your cart' : 'Favourites' }}</h1><p class="lead">{{ $studio->name }}</p></div></section>
<section class="section"><div class="container">
    <nav class="concert-shop-tabs" aria-label="Concert gallery"><a href="{{ $concertBackUrl }}">Albums</a><a href="{{ request()->url() }}?tab=favourites" @if($activeTab === 'favourites') aria-current="page" @endif>Favourites @if($favourites->count())<span class="concert-tab-count">{{ $favourites->count() }}</span>@endif</a><a href="{{ request()->url() }}?tab=cart" @if($activeTab === 'cart') aria-current="page" @endif>Cart @if($cart->count())<span class="concert-tab-count">{{ $cart->count() }}</span>@endif</a></nav>
    @if(session('status'))<p role="status">{{ session('status') }}</p>@endif
    @if($errors->any())<p class="error" role="alert">{{ $errors->first() }}</p>@endif
    @if($activeTab === 'favourites')
    <h2>Saved favourites</h2><div class="concert-favourites">
        @forelse($favourites as $selection)
            <article class="card concert-favourite" data-selection-card data-asset="{{ $selection->asset->uuid }}" data-cart="{{ $selection->in_cart ? 'true' : 'false' }}" data-favourite="true"><button type="button" data-preview="{{ route($concertRoutes['image'], ['concert' => $selection->concert, 'asset' => $selection->asset->uuid]) }}" aria-label="Preview favourite photo"><img src="{{ route($concertRoutes['image'], ['concert' => $selection->concert, 'asset' => $selection->asset->uuid, 'size' => 'thumbnail']) }}" alt="Watermarked concert photo"></button><div class="concert-favourite-copy">
                <strong>{{ $selection->concert->name }}</strong><span>{{ $selection->asset->collection?->name ?? 'Concert album' }}</span><span>Photo</span><div class="concert-actions">
                <form method="post" action="{{ route($concertRoutes['selection'], $selection->concert) }}" data-selection-form data-kind="cart">@csrf<input type="hidden" name="asset_uuid" value="{{ $selection->asset->uuid }}"><input type="hidden" name="action" value="{{ $selection->in_cart ? 'remove' : 'add' }}"><button class="concert-icon-button {{ $selection->in_cart ? 'is-cart' : '' }}" data-tooltip="{{ $selection->in_cart ? 'Remove from cart' : 'Add to cart' }}" aria-label="{{ $selection->in_cart ? 'Remove from cart' : 'Add to cart' }}"><svg><use href="#concert-cart-icon"/></svg></button></form>
                <form method="post" action="{{ route($concertRoutes['selection'], $selection->concert) }}" data-selection-form data-kind="favourite">@csrf
                    <input type="hidden" name="asset_uuid" value="{{ $selection->asset->uuid }}"><input type="hidden" name="action" value="unfavourite"><button class="concert-icon-button is-favourite" data-tooltip="Remove from favourites" aria-label="Remove from favourites"><svg><use href="#concert-heart-icon"/></svg></button></form>
                </div></div></article>
        @empty<p>No concert photo favourites saved for this studio.</p>@endforelse
    </div>
    @else
    <h2>Your cart</h2>
    <div class="studio-cart-list">
        @forelse($cart as $selection)
            <div class="card studio-cart-row" data-selection-card data-asset="{{ $selection->asset->uuid }}" data-cart="true" data-favourite="{{ $selection->is_favourite ? 'true' : 'false' }}"><button type="button" data-preview="{{ route($concertRoutes['image'], ['concert' => $selection->concert, 'asset' => $selection->asset->uuid]) }}" aria-label="Preview cart photo"><img src="{{ route($concertRoutes['image'], ['concert' => $selection->concert, 'asset' => $selection->asset->uuid, 'size' => 'thumbnail']) }}" alt="Watermarked concert photo"></button>
                <div class="details">
                    <strong>{{ $selection->concert->name }}</strong>
                    <p class="meta">{{ $selection->asset->collection?->name ?? 'Concert album' }}</p><p class="meta">Photo · ${{ number_format(($lineAmounts[$selection->id] ?? $photoPrice) / 100, 2) }}
                        @if($principalPrice)
                            · Studio principal price
                        @endif
                    </p>
                </div>
                <form method="post" action="{{ route($concertRoutes['selection'], $selection->concert) }}" data-selection-form data-kind="cart">@csrf
                    <input type="hidden" name="asset_uuid" value="{{ $selection->asset->uuid }}"><input type="hidden" name="action" value="remove"><button class="concert-icon-button is-remove" data-tooltip="Remove from cart" aria-label="Remove from cart"><svg><use href="#concert-bin-icon"/></svg></button>
                </form>
            </div>
        @empty<p>Your concert photo cart is empty.</p>@endforelse
    </div>
    @if($cart->isNotEmpty())<div class="card studio-cart-total">
        <p><span>Subtotal</span><strong>${{ number_format($subtotal / 100, 2) }}</strong></p>
        <p><span>Bulk discount ({{ $percent }}%)</span><strong>−${{ number_format($discount / 100, 2) }}</strong></p>
        @if($principalPrice)<p style="display:block">Studio principal $1 photos do not count towards bulk discounts.</p>@endif
        <p class="total"><span>Total including GST</span><span>${{ number_format($total / 100, 2) }}</span></p>
        <label style="display:block;margin-top:16px">Discount code<input name="discount_code" form="concert-checkout-form" value="{{ old('discount_code', session('checkout.discount_code')) }}" maxlength="32" autocomplete="off" style="display:block;margin-top:6px"><span class="meta">A code reduces the amount used to calculate the bulk discount.</span></label>
        @if(!($guestCheckout ?? false) && $creditBalance > 0)<label style="display:flex;align-items:flex-start;gap:9px;margin-top:16px"><input type="checkbox" name="use_account_credit" value="1" form="concert-checkout-form" @checked(old('use_account_credit')) style="width:auto;min-height:0;margin-top:4px"><span>Use my ${{ number_format($creditBalance / 100, 2) }} account credit<span class="meta" style="display:block">Credit is applied after discounts. GST is calculated on the remaining total.</span></span></label>@endif
        @if($guestCheckout ?? false)
            <form id="concert-checkout-form" method="get" action="{{ route($concertRoutes['checkout'], $cart->first()->concert) }}" style="display:block;margin-top:16px"><button class="button" type="submit">Create an account to continue to checkout</button> <button class="button secondary" type="submit" name="account" value="login">Sign in</button></form>
        @else
            <form id="concert-checkout-form" method="post" action="{{ route('customer.concerts.photos.checkout', $studio) }}" style="display:block;margin-top:16px">@csrf<button class="button" type="submit">Review order</button></form>
        @endif
    </div>@endif
    @endif
    <dialog class="concert-viewer" id="concert-viewer"><div class="concert-viewer-header"><strong>Watermarked preview</strong><button type="button" data-close>Close ✕</button></div><div class="concert-viewer-frame"><img id="concert-viewer-image" alt="Watermarked concert photo preview"><div class="concert-viewer-actions"><form method="post" data-selection-form data-preview-form data-kind="cart">@csrf<input type="hidden" name="asset_uuid"><input type="hidden" name="action" value="add"><button class="concert-icon-button" data-tooltip="Add to cart" aria-label="Add to cart"><svg><use href="#concert-cart-icon"/></svg></button></form><form method="post" data-selection-form data-preview-form data-kind="favourite">@csrf<input type="hidden" name="asset_uuid"><input type="hidden" name="action" value="favourite"><button class="concert-icon-button" data-tooltip="Add to favourites" aria-label="Add to favourites"><svg><use href="#concert-heart-icon"/></svg></button></form></div></div></dialog><div class="concert-feedback" id="concert-feedback" role="status" hidden></div>
</div></section>
@endsection

@push('scripts')
<script nonce="{{ request()->attributes->get('csp_nonce') }}">(()=>{const viewer=document.getElementById('concert-viewer'),image=document.getElementById('concert-viewer-image'),feedback=document.getElementById('concert-feedback');let active,hideTimer;const notice=message=>{feedback.textContent=message;feedback.hidden=false;clearTimeout(hideTimer);hideTimer=setTimeout(()=>feedback.hidden=true,3000)};const setButton=(form,on)=>{const button=form.querySelector('button'),kind=form.dataset.kind;form.elements.action.value=kind==='cart'?(on?'remove':'add'):(on?'unfavourite':'favourite');button.classList.toggle(kind==='cart'?'is-cart':'is-favourite',on);button.dataset.tooltip=kind==='cart'?(on?'Remove from cart':'Add to cart'):(on?'Remove from favourites':'Add to favourites');button.setAttribute('aria-label',button.dataset.tooltip)};const submit=async form=>{const response=await fetch(form.getAttribute('action'),{method:'POST',body:new FormData(form),credentials:'same-origin',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}}),result=await response.json().catch(()=>null);if(!response.ok||!result?.success)throw new Error(result?.message||'Could not save your choice.');active.dataset.cart=result.data.in_cart?'true':'false';active.dataset.favourite=result.data.is_favourite?'true':'false';document.querySelectorAll('[data-selection-form]').forEach(item=>{if(item.elements.asset_uuid.value===result.data.asset_uuid)setButton(item,item.dataset.kind==='cart'?result.data.in_cart:result.data.is_favourite)});notice(result.message);if((!result.data.is_favourite&&active.closest('.concert-favourites'))||(!result.data.in_cart&&active.closest('.studio-cart-list'))){viewer.close();active.remove()}};document.querySelectorAll('[data-selection-form]').forEach(form=>form.addEventListener('submit',async event=>{event.preventDefault();active=form.closest('[data-selection-card]')||active;try{await submit(form)}catch(error){notice(error.message)}}));document.querySelectorAll('[data-preview]').forEach(button=>button.addEventListener('click',()=>{active=button.closest('[data-selection-card]');image.src=button.dataset.preview;viewer.querySelectorAll('[data-preview-form]').forEach(form=>{form.setAttribute('action',active.querySelector('[data-selection-form]').getAttribute('action'));form.elements.asset_uuid.value=active.dataset.asset;setButton(form,form.dataset.kind==='cart'?active.dataset.cart==='true':active.dataset.favourite==='true')});viewer.showModal()}));viewer.querySelector('[data-close]').addEventListener('click',()=>viewer.close());viewer.addEventListener('click',event=>{if(event.target===viewer)viewer.close()});viewer.addEventListener('close',()=>image.removeAttribute('src'))})();</script>
@endpush
