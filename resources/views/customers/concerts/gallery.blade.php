@extends('layouts.public', ['title' => $concert->photoGalleryTitle().' photos', 'footerLabel' => 'Concert photos'])

@php
    $headerConcertRoutes = $concertRoutes ?? ['show' => 'customer.concerts.photos.show'];
    $headerConcertHomeUrl = $concertHomeUrl ?? route('customer.media.index');
    $headerParentUrl = $selected && ! ($flatPortraitGallery ?? false)
        ? route($headerConcertRoutes['show'], array_filter(['concert' => $concert, 'group' => $selectedGroup?->uuid])).'#gallery-content'
        : $headerConcertHomeUrl;
@endphp
@section('headerParentUrl', $headerParentUrl)

@push('styles')
<style>
    .concert-tabs { display:flex; width:100%; max-width:100%; align-items:flex-end; overflow-x:auto; margin:0 0 28px; border-bottom:2px solid #cddbe1; }
    .concert-tabs a { flex:0 0 auto; padding:11px 20px; border-radius:9px 9px 0 0; color:#38505d; font-weight:800; text-decoration:none; }
    .concert-tabs a[aria-current="page"] { margin-bottom:-2px; border:2px solid #cddbe1; border-bottom-color:#fff; background:#fff; color:#087eae; }
    .concert-tab-count { display:inline-grid; min-width:20px; height:20px; margin-left:5px; place-items:center; border-radius:10px; background:#087eae; color:#fff; font-size:11px; }
    .concert-selection-links { display:flex; flex:0 0 auto; gap:7px; margin-left:auto; padding-left:18px; }
    .concert-tabs .concert-selection-links a { display:inline-flex; align-items:center; gap:8px; margin:0 0 5px; padding:9px 13px; border:1px solid #bfd0d8; border-radius:9px; background:#fff; }
    .concert-selection-links svg { width:19px; height:19px; fill:none; stroke:currentColor; stroke-width:1.8; stroke-linecap:round; stroke-linejoin:round; }
    .concert-photos { display:grid; grid-template-columns:repeat(auto-fill,minmax(210px,240px)); gap:16px; align-items:start; }
    .concert-products { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,270px)); gap:14px; margin:4px 0 30px; }
    .concert-product { display:flex; min-height:220px; flex-direction:column; align-items:center; padding:22px 18px 17px; text-align:center; }
    .concert-product-symbol { display:grid; width:52px; height:52px; margin-bottom:13px; place-items:center; border-radius:15px; background:#e5f6fd; color:#087bab; }
    .concert-product-symbol svg { width:29px; height:29px; fill:none; stroke:currentColor; stroke-width:1.8; }
    .concert-product h3 { margin:0 0 4px; font-size:17px; }
    .concert-product-price { margin:0 0 3px; color:#087bab; font-size:22px; font-weight:850; }
    .concert-product-note { margin:0 0 12px; color:var(--muted); font-size:12px; }
    .concert-discount-tiers { width:100%; margin:2px 0 12px; padding:0; list-style:none; text-align:left; }
    .concert-discount-tiers li { display:flex; justify-content:space-between; gap:12px; padding:5px 0; border-bottom:1px solid #e3ebef; color:#53656e; font-size:13px; }
    .concert-discount-tiers strong { color:#087bab; }
    .concert-product-actions { display:flex; gap:10px; margin-top:auto; }
    .concert-product-actions [data-start-selection="favourite"] { order:1; }
    .concert-product-actions [data-start-selection="add"] { order:2; }
    .concert-photo-tile { min-width:0; }
    .concert-photo-frame { position:relative; }
    .concert-photo { display:block; overflow:hidden; cursor:zoom-in; }
    .concert-photo img { display:block; width:100%; height:auto; }
    .concert-photo-tile.is-picked .concert-photo { outline:3px solid #078bc4; outline-offset:2px; }
    .concert-photo-check { display:none; position:absolute; z-index:1; top:9px; right:9px; width:25px; height:25px; place-items:center; border:2px solid #fff; border-radius:50%; background:#183342aa; color:#fff; font-weight:800; pointer-events:none; }
    .concert-photo-tile.is-picked .concert-photo-check,.concert-photos.is-selecting .concert-photo-check { display:grid; }
    .concert-photo-actions { position:absolute; z-index:2; right:9px; bottom:9px; display:flex; gap:6px; opacity:.68; transition:opacity .18s ease; }
    .concert-photo-actions form[data-kind="favourite"],.concert-viewer-actions form[data-kind="favourite"] { order:1; }
    .concert-photo-actions form[data-kind="cart"],.concert-viewer-actions form[data-kind="cart"] { order:2; }
    .concert-photo-tile:hover .concert-photo-actions,.concert-photo-tile:focus-within .concert-photo-actions { opacity:1; }
    .concert-photos.is-selecting .concert-photo-actions { display:none; }
    .concert-icon-button { position:relative; display:grid; width:39px; height:36px; place-items:center; border:1px solid #ffffff9c; border-radius:8px; background:#102a3bd9; color:#fff; cursor:pointer; backdrop-filter:blur(4px); }
    .concert-product-actions .concert-icon-button { border-color:#c9dbe4; background:#fff; color:#285068; }
    .concert-icon-button.is-cart { border-color:#79d4f6; background:#078bc4; color:#fff; }
    .concert-icon-button.is-favourite { background:#385fbe; color:#fff; }
    .concert-icon-button svg { width:20px; height:20px; fill:none; stroke:currentColor; stroke-width:1.9; stroke-linecap:round; stroke-linejoin:round; }
    .concert-icon-button.is-favourite svg { fill:currentColor; }
    .concert-icon-button:disabled { cursor:not-allowed; opacity:.5; }
    .concert-icon-button[data-tooltip]:hover::after,.concert-icon-button[data-tooltip]:focus-visible::after { position:absolute; z-index:8; right:0; bottom:calc(100% + 7px); padding:7px 9px; border-radius:6px; background:#10212c; color:#fff; content:attr(data-tooltip); font-size:12px; font-weight:700; white-space:nowrap; box-shadow:0 4px 13px #0004; }
    .concert-product-actions .concert-icon-button[data-tooltip]:hover::after,.concert-product-actions .concert-icon-button[data-tooltip]:focus-visible::after { right:auto; left:50%; transform:translateX(-50%); }
    .concert-selection-bar { display:flex; flex-wrap:wrap; align-items:center; gap:12px; margin:0 0 16px; padding:13px 16px; border:1px solid #80c5e1; border-radius:10px; background:#e7f7fe; }
    .concert-selection-bar[hidden],.concert-feedback[hidden] { display:none; }
    .concert-selection-bar strong { margin-right:auto; }
    .concert-selection-bar .button { min-height:38px; padding:7px 13px; }
    .concert-selection-bar button[type="button"] { border:0; background:transparent; color:#285068; font:inherit; font-weight:750; cursor:pointer; }
    .concert-feedback { position:fixed; z-index:1001; right:20px; bottom:20px; max-width:min(350px,calc(100% - 40px)); padding:12px 16px; border-radius:9px; background:#10212c; color:#fff; box-shadow:0 8px 30px #0005; font-weight:700; }
    .concert-viewer { width:min(1100px,calc(100% - 24px)); max-height:calc(100% - 24px); padding:0; border:0; border-radius:12px; background:#101820; color:#fff; }
    .concert-viewer::backdrop { background:#000c; }
    .concert-viewer-inner { display:flex; max-height:calc(100vh - 24px); flex-direction:column; }
    .concert-viewer-header { display:flex; justify-content:space-between; align-items:center; padding:10px 16px; }
    .concert-viewer-header>button,.concert-viewer-controls button { border:1px solid #5b6971; border-radius:7px; background:#253842; color:#fff; cursor:pointer; padding:8px 13px; }
    .concert-viewer-frame { position:relative; width:fit-content; max-width:100%; margin:auto; }
    .concert-viewer img { display:block; max-width:100%; max-height:calc(100vh - 160px); width:auto; height:auto; object-fit:contain; }
    .concert-viewer-actions { position:absolute; right:10px; bottom:10px; display:flex; gap:7px; }
    .concert-viewer-controls { display:flex; justify-content:space-between; gap:12px; padding:12px 16px; }
    @media(hover:none) { .concert-photo-actions { opacity:1; } }
    @media(max-width:650px) { .concert-photos { grid-template-columns:repeat(2,minmax(0,1fr)); gap:9px; } .concert-products { grid-template-columns:1fr; } }
</style>
@endpush

@section('content')
@php
    $concertRoutes = $concertRoutes ?? ['show' => 'customer.concerts.photos.show', 'image' => 'customer.concerts.photos.image', 'selection' => 'customer.concerts.photos.selections.store', 'cart' => 'customer.concerts.photos.cart'];
    $concertHomeUrl = $concertHomeUrl ?? route('customer.media.index');
    $concertCartTarget = $concertCartTarget ?? $concert->studio;
    $usesAccountSelections = auth()->check() && $concertRoutes['show'] === 'customer.concerts.photos.show';
    $cartUrl = $usesAccountSelections
        ? route('customer.media.cart', ['focus_kind' => 'concert', 'focus_event' => $concert->uuid])
        : route($concertRoutes['cart'], [$concertCartTarget, 'tab' => 'cart']);
    $favouritesUrl = $usesAccountSelections
        ? route('customer.media.favourites', ['focus_kind' => 'concert', 'focus_event' => $concert->uuid])
        : route($concertRoutes['cart'], [$concertCartTarget, 'tab' => 'favourites']);
    $displayCartCount = $usesAccountSelections ? $shopping['cartCount'] : $shopping['cart']->count();
    $displayFavouriteCount = $usesAccountSelections ? $shopping['favouriteCount'] : $shopping['selections']->where('is_favourite', true)->count();
    $priceText = '$'.number_format($shopping['photoPrice'] / 100, 2);
    $commerceSettings = app(\App\Features\Media\Services\MediaCommerceSettings::class);
    $bulkDiscountTiers = collect(range(1, 4))->map(fn (int $tier) => [
        'threshold' => (int) $commerceSettings->get("bulk_{$tier}_threshold"),
        'percent' => (int) $commerceSettings->get("bulk_{$tier}_percent"),
    ])->filter(fn (array $tier) => $tier['percent'] > 0);
@endphp
<section class="hero"><div class="container">@include('customers._heading', ['headingImageUrl' => $concert->studio->logoUrl(), 'eyebrow' => 'Concert photos', 'heading' => $concert->photoGalleryTitle(), 'description' => ($flatPortraitGallery ? 'Browse your dress rehearsal portraits.' : 'Browse your concert photos.').($concert->event_date ? ' · '.$concert->event_date->format('j M Y') : '')])</div></section>
<section class="section" id="gallery-content"><div class="container">
    <nav class="concert-tabs" aria-label="Concert gallery"><a href="{{ route($concertRoutes['show'], $concert) }}" aria-current="page">{{ $flatPortraitGallery ? 'Photos' : 'Albums' }}</a><span class="concert-selection-links"><a href="{{ $favouritesUrl }}" data-favourites-tab><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.8 8.5c0 5-8.8 11-8.8 11s-8.8-6-8.8-11a4.6 4.6 0 0 1 8.8-1.8 4.6 4.6 0 0 1 8.8 1.8Z"/></svg>Favourites @if($displayFavouriteCount)<span class="concert-tab-count">{{ $displayFavouriteCount }}</span>@endif</a><a href="{{ $cartUrl }}" data-cart-tab><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.1 11h11.5l2-8H6"/><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>Cart @if($displayCartCount)<span class="concert-tab-count">{{ $displayCartCount }}</span>@endif</a></span></nav>
    @php
        $breadcrumbConcert = $selected?->concert ?? $concert;
        $galleryBreadcrumbs = [
            ['label' => $concert->studio->name, 'url' => route($usesAccountSelections ? 'customer.profile.studio' : 'guest-galleries.studios.show', $concert->studio)],
            ['label' => $breadcrumbConcert->name, 'url' => route($concertRoutes['show'], $breadcrumbConcert).'#gallery-content'],
        ];
        if ($selected && !$flatPortraitGallery) $galleryBreadcrumbs[] = ['label' => $selected->name];
    @endphp
    <x-gallery-breadcrumbs :items="$galleryBreadcrumbs" />
    @if(session('status'))<p role="status">{{ session('status') }}</p>@endif
    @if($errors->any())<p class="error" role="alert">{{ $errors->first() }}</p>@endif
    @if($selected)
        @if(!$flatPortraitGallery)<h2>{{ $selected->name }}</h2>@endif
        @if($photos->isNotEmpty())
        <svg aria-hidden="true" style="position:absolute;width:0;height:0;overflow:hidden"><symbol id="concert-photo-icon" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m4 18 5-5 3 3 3-4 5 5"/></symbol><symbol id="concert-cart-icon" viewBox="0 0 24 24"><path d="M3 4h2l2.1 11h11.5l2-8H6"/><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></symbol><symbol id="concert-heart-icon" viewBox="0 0 24 24"><path d="M20.8 8.5c0 5-8.8 11-8.8 11s-8.8-6-8.8-11a4.6 4.6 0 0 1 8.8-1.8 4.6 4.6 0 0 1 8.8 1.8Z"/></symbol></svg>
        <div class="concert-products">
            <article class="card concert-product"><span class="concert-product-symbol"><svg viewBox="0 0 24 24"><use href="#concert-photo-icon"/></svg></span><h3>Individual photos</h3><p class="concert-product-price">{{ $priceText }} <span style="font-size:14px">each</span></p><p class="concert-product-note">Choose one photo or select several</p><div class="concert-product-actions"><button class="concert-icon-button" type="button" data-start-selection="add" data-tooltip="Add to cart · {{ $priceText }} each" aria-label="Choose photos to add to cart"><svg><use href="#concert-cart-icon"/></svg></button><button class="concert-icon-button" type="button" data-start-selection="favourite" data-tooltip="Add to favourites" aria-label="Choose photos to add to favourites"><svg><use href="#concert-heart-icon"/></svg></button></div></article>
            @if(empty($shopping['principalPrice']) && $bulkDiscountTiers->isNotEmpty())
                <article class="card concert-product"><span class="concert-product-symbol" aria-hidden="true" style="font-size:25px;font-weight:850">%</span><h3>Bulk photo discounts</h3><ul class="concert-discount-tiers">@foreach($bulkDiscountTiers as $tier)<li><span>Spend ${{ number_format($tier['threshold'] / 100, 0) }}</span><strong>Save {{ $tier['percent'] }}%</strong></li>@endforeach</ul><p class="concert-product-note">Applied automatically to eligible photos from this event.</p></article>
            @endif
        </div>
        <div class="concert-selection-bar" id="concert-selection-bar" hidden><strong id="concert-selection-count">0 photos selected on this page</strong><button class="button" type="button" id="concert-selection-submit">Add selected to cart</button><button type="button" id="concert-selection-cancel">Cancel</button></div>
        <div class="concert-photos" id="concert-photos">
            @foreach($photos as $photo)
                @php($preview = route($concertRoutes['image'], ['concert' => $concert, 'asset' => $photo->uuid]))
                @php($selection = $shopping['selections']->first(fn($item) => $item->media_asset_id === $photo->id))
                @php($owned = $shopping['ownedAssetIds']->contains($photo->id))
                <article class="concert-photo-tile" data-asset="{{ $photo->uuid }}" data-preview="{{ $preview }}" data-cart="{{ $selection?->in_cart ? 'true' : 'false' }}" data-favourite="{{ $selection?->is_favourite ? 'true' : 'false' }}" data-owned="{{ $owned ? 'true' : 'false' }}">
                    <div class="concert-photo-frame"><a class="card concert-photo" href="{{ $preview }}" target="_blank" rel="noopener" aria-label="View watermarked photo"><img loading="lazy" src="{{ route($concertRoutes['image'], ['concert' => $concert, 'asset' => $photo->uuid, 'size' => 'thumbnail']) }}" alt="Concert photo preview"></a><span class="concert-photo-check">✓</span>
                        <div class="concert-photo-actions">
                            <form method="post" action="{{ route($concertRoutes['selection'], $concert) }}" data-selection-form data-kind="cart">@csrf<input type="hidden" name="asset_uuid" value="{{ $photo->uuid }}"><input type="hidden" name="action" value="{{ $selection?->in_cart ? 'remove' : 'add' }}"><button class="concert-icon-button {{ $selection?->in_cart ? 'is-cart' : '' }}" data-tooltip="{{ $owned ? 'Already owned' : ($selection?->in_cart ? 'Remove from cart' : 'Add to cart · '.$priceText) }}" aria-label="{{ $selection?->in_cart ? 'Remove from cart' : 'Add to cart' }}" @disabled($owned)><svg><use href="#concert-cart-icon"/></svg></button></form>
                            <form method="post" action="{{ route($concertRoutes['selection'], $concert) }}" data-selection-form data-kind="favourite">@csrf<input type="hidden" name="asset_uuid" value="{{ $photo->uuid }}"><input type="hidden" name="action" value="{{ $selection?->is_favourite ? 'unfavourite' : 'favourite' }}"><button class="concert-icon-button {{ $selection?->is_favourite ? 'is-favourite' : '' }}" data-tooltip="{{ $selection?->is_favourite ? 'Remove from favourites' : 'Add to favourites' }}" aria-label="{{ $selection?->is_favourite ? 'Remove from favourites' : 'Add to favourites' }}"><svg><use href="#concert-heart-icon"/></svg></button></form>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
        <nav class="actions" aria-label="Photo pages">@if($photos->previousPageUrl())<a class="button secondary" href="{{ $photos->previousPageUrl().'#gallery-content' }}">← Previous photos</a>@endif @if($photos->nextPageUrl())<a class="button secondary" href="{{ $photos->nextPageUrl().'#gallery-content' }}">Next photos →</a>@endif</nav>
        <dialog class="concert-viewer" id="concert-viewer" aria-label="Photo preview"><div class="concert-viewer-inner"><div class="concert-viewer-header"><strong>Watermarked preview</strong><button type="button" data-close>Close ✕</button></div><div class="concert-viewer-frame"><img id="concert-viewer-image" alt="Watermarked concert photo preview"><div class="concert-viewer-actions"><form method="post" action="{{ route($concertRoutes['selection'], $concert) }}" data-selection-form data-preview-form data-kind="cart">@csrf<input type="hidden" name="asset_uuid"><input type="hidden" name="action" value="add"><button class="concert-icon-button" data-tooltip="Add to cart · {{ $priceText }}" aria-label="Add photo to cart"><svg><use href="#concert-cart-icon"/></svg></button></form><form method="post" action="{{ route($concertRoutes['selection'], $concert) }}" data-selection-form data-preview-form data-kind="favourite">@csrf<input type="hidden" name="asset_uuid"><input type="hidden" name="action" value="favourite"><button class="concert-icon-button" data-tooltip="Add to favourites" aria-label="Add photo to favourites"><svg><use href="#concert-heart-icon"/></svg></button></form></div></div><div class="concert-viewer-controls"><button type="button" data-previous>← Previous photo</button><button type="button" data-next>Next photo →</button></div></div></dialog>
        <div class="concert-feedback" id="concert-feedback" role="status" hidden></div>
        @else<div class="empty"><p>No photos are available in this album yet.</p></div>@endif
    @elseif($selectedGroup)
        <p><a href="{{ route($concertRoutes['show'], $concert).'#gallery-content' }}">← All concert albums</a></p><h2>{{ $selectedGroup->name }}</h2>
        @include('customers.concerts._album-cards')
    @else
        <h2>Concert albums</h2>
        @include('customers.concerts._album-cards')

    @endif
</div></section>
@endsection

@push('scripts')
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
(() => {
    const grid=document.getElementById('concert-photos'),viewer=document.getElementById('concert-viewer'),feedback=document.getElementById('concert-feedback');
    if(!grid) return;
    const tiles=Array.from(grid.querySelectorAll('.concert-photo-tile')),forms=Array.from(document.querySelectorAll('[data-selection-form]')); let current=0,mode=null,hideTimer;
    const badge=(selector,count)=>{const tab=document.querySelector(selector);if(!tab)return;let item=tab.querySelector('.concert-tab-count');if(count){if(!item){item=document.createElement('span');item.className='concert-tab-count';tab.append(item)}item.textContent=count}else item?.remove()};
    const notice=message=>{feedback.textContent=message;feedback.hidden=false;clearTimeout(hideTimer);hideTimer=setTimeout(()=>feedback.hidden=true,3500)};
    const setButton=(form,active)=>{const button=form.querySelector('button'),kind=form.dataset.kind;form.elements.action.value=kind==='cart'?(active?'remove':'add'):(active?'unfavourite':'favourite');button.classList.toggle(kind==='cart'?'is-cart':'is-favourite',active);button.dataset.tooltip=kind==='cart'?(active?'Remove from cart':'Add to cart · {{ $priceText }}'):(active?'Remove from favourites':'Add to favourites');button.setAttribute('aria-label',button.dataset.tooltip)};
    const sync=data=>{tiles.forEach(tile=>{if(tile.dataset.asset!==data.asset_uuid)return;tile.dataset.cart=data.in_cart?'true':'false';tile.dataset.favourite=data.is_favourite?'true':'false'});forms.forEach(form=>{if(form.elements.asset_uuid.value!==data.asset_uuid)return;setButton(form,form.dataset.kind==='cart'?data.in_cart:data.is_favourite)});badge('[data-cart-tab]',data.cart_count);badge('[data-favourites-tab]',data.favourite_count)};
    const submit=async(form)=>{const response=await fetch(form.getAttribute('action'),{method:'POST',body:new FormData(form),credentials:'same-origin',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}}),result=await response.json().catch(()=>null);if(!response.ok||!result?.success)throw new Error(result?.message||'Could not save your choice.');sync(result.data);return result};
    forms.forEach(form=>form.addEventListener('submit',async event=>{event.preventDefault();const button=form.querySelector('button');if(button.disabled)return;button.disabled=true;try{const result=await submit(form);notice(result.message)}catch(error){notice(error.message)}finally{button.disabled=false}}));
    const bar=document.getElementById('concert-selection-bar'),count=document.getElementById('concert-selection-count'),commit=document.getElementById('concert-selection-submit');
    const update=()=>{const selected=tiles.filter(tile=>tile.classList.contains('is-picked')).length;count.textContent=`${selected} photo${selected===1?'':'s'} selected on this page`;commit.disabled=!selected;commit.textContent=mode==='favourite'?'Add selected to favourites':'Add selected to cart'};
    document.querySelectorAll('[data-start-selection]').forEach(button=>button.addEventListener('click',()=>{mode=button.dataset.startSelection;grid.classList.add('is-selecting');bar.hidden=false;tiles.forEach(tile=>tile.classList.remove('is-picked'));update();grid.scrollIntoView({behavior:'smooth',block:'start'})}));
    tiles.forEach((tile,index)=>tile.querySelector('.concert-photo').addEventListener('click',event=>{event.preventDefault();if(mode){if(mode==='add'&&tile.dataset.owned==='true')return;tile.classList.toggle('is-picked');update();return}current=index;openViewer()}));
    document.getElementById('concert-selection-cancel')?.addEventListener('click',()=>{mode=null;bar.hidden=true;grid.classList.remove('is-selecting');tiles.forEach(tile=>tile.classList.remove('is-picked'))});
    commit?.addEventListener('click',async()=>{const picked=tiles.filter(tile=>tile.classList.contains('is-picked'));commit.disabled=true;try{for(const tile of picked){const form=tile.querySelector(`[data-kind="${mode==='favourite'?'favourite':'cart'}"]`);form.elements.action.value=mode==='favourite'?'favourite':'add';await submit(form)}notice(`${picked.length} photo${picked.length===1?'':'s'} updated.`);document.getElementById('concert-selection-cancel').click()}catch(error){notice(error.message);commit.disabled=false}});
    const image=document.getElementById('concert-viewer-image'),openViewer=()=>{const tile=tiles[current];image.src=tile.dataset.preview;viewer.querySelectorAll('[data-preview-form]').forEach(form=>{form.elements.asset_uuid.value=tile.dataset.asset;setButton(form,form.dataset.kind==='cart'?tile.dataset.cart==='true':tile.dataset.favourite==='true');form.querySelector('button').disabled=form.dataset.kind==='cart'&&tile.dataset.owned==='true'});viewer.querySelector('[data-previous]').disabled=current===0;viewer.querySelector('[data-next]').disabled=current===tiles.length-1;if(!viewer.open)viewer.showModal()};
    viewer?.querySelector('[data-close]')?.addEventListener('click',()=>viewer.close());viewer?.querySelector('[data-previous]')?.addEventListener('click',()=>{if(current>0){current--;openViewer()}});viewer?.querySelector('[data-next]')?.addEventListener('click',()=>{if(current<tiles.length-1){current++;openViewer()}});viewer?.addEventListener('click',event=>{if(event.target===viewer)viewer.close()});viewer?.addEventListener('close',()=>{image.removeAttribute('src');tiles[current]?.querySelector('.concert-photo')?.focus({preventScroll:true})});
    viewer?.addEventListener('keydown',event=>{
        if(event.ctrlKey||event.metaKey||event.altKey||event.shiftKey||event.target.closest('input,textarea,select,[contenteditable="true"]'))return;
        if(event.key==='ArrowLeft'){event.preventDefault();if(current>0){current--;openViewer()}}
        if(event.key==='ArrowRight'){event.preventDefault();if(current<tiles.length-1){current++;openViewer()}}
    });
})();
</script>
@endpush
