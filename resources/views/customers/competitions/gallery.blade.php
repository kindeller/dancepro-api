@extends('layouts.public', ['title' => $title, 'footerLabel' => 'Competition media'])

@php
    $headerCompetitionRoutes = $competitionRoutes ?? ['show' => 'customer.competitions.show'];
    $headerCompetitionHomeUrl = $competitionHomeUrl ?? route('customer.media.index');
    $headerParentCrumb = $selected ? collect($breadcrumbs)->slice(0, -1)->last() : null;
    $headerParentUrl = $selected
        ? ($headerParentCrumb
            ? route($headerCompetitionRoutes['show'], ['schedulingEvent' => $event, 'folder' => $headerParentCrumb['uuid']]).'#gallery-content'
            : route($headerCompetitionRoutes['show'], $event).'#gallery-content')
        : $headerCompetitionHomeUrl;
@endphp
@section('headerParentUrl', $headerParentUrl)

@push('styles')
<style>
    .gallery-crumbs { display: flex; flex-wrap: wrap; align-items: center; gap: 9px; margin-bottom: 24px; }
    .gallery-crumbs a { text-underline-offset: 3px; }
    .gallery-photos { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 12px; align-items: start; }
    .gallery-photo { display: block; overflow: hidden; padding: 0; cursor: zoom-in; }
    .gallery-photo img { display: block; width: 100%; height: auto; }
    .gallery-products { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,270px)); gap:14px; margin:6px 0 32px; }
    .gallery-product { display:flex; flex-direction:column; align-items:center; min-height:220px; padding:22px 18px 17px; text-align:center; border-color:#d0e0e8; box-shadow:0 12px 28px #112c3a0a; }
    .gallery-product-symbol { display:grid; place-items:center; width:52px; height:52px; margin-bottom:13px; border-radius:15px; background:#e5f6fd; color:#087bab; }
    .gallery-product-symbol svg { width:29px; height:29px; }
    .gallery-product h3 { max-width:220px; min-height:46px; margin:0 0 4px; font-size:17px; line-height:1.32; }
    .gallery-product-price { margin:0 0 3px; color:#087bab; font-size:22px; font-weight:850; }
    .gallery-product-note { min-height:21px; margin:0 0 12px; color:var(--muted); font-size:12px; }
    .gallery-product-actions { display:flex; gap:10px; margin-top:auto; }
    .gallery-product-actions form { margin:0; }
    .gallery-product-actions form[data-selection-kind="favourite"],.gallery-product-actions [data-start-selection="favourite"] { order:1; }
    .gallery-product-actions form[data-selection-kind="cart"],.gallery-product-actions [data-start-selection="add"] { order:2; }
    .gallery-icon-button { display:inline-grid; width:43px; height:39px; place-items:center; border:1px solid #c9dbe4; border-radius:9px; background:#fff; color:#285068; cursor:pointer; }
    .gallery-icon-button:hover, .gallery-icon-button:focus-visible { border-color:#0589c2; background:#e8f7fd; color:#006b98; }
    .gallery-icon-button.is-selected { border-color:#078bc4; background:#078bc4; color:#fff; }
    .gallery-icon-button.is-favourite { border-color:#a8bde7; background:#edf2ff; color:#335fc7; }
    .gallery-icon-button svg { width:21px; height:21px; fill:none; stroke:currentColor; stroke-width:1.9; stroke-linecap:round; stroke-linejoin:round; }
    .gallery-icon-button.is-favourite svg { fill:currentColor; }
    .gallery-individual-heading { display:flex; align-items:baseline; gap:12px; margin-bottom:14px; }
    .gallery-individual-heading h2 { margin:0; }
    .gallery-individual-heading p { margin:0; color:var(--muted); }
    .gallery-photo-tile { display:block; }
    .gallery-photo-frame { position:relative; }
    .gallery-photo-tile.is-picked .gallery-photo { outline:3px solid #078bc4; outline-offset:2px; }
    .gallery-photo-check { display:none; position:absolute; z-index:1; top:9px; right:9px; width:25px; height:25px; place-items:center; border:2px solid #fff; border-radius:50%; background:#183342aa; color:#fff; font-size:16px; font-weight:800; box-shadow:0 1px 5px #0005; pointer-events:none; }
    .gallery-photo-tile.is-picked .gallery-photo-check { display:grid; background:#078bc4; }
    .gallery-photos.is-selecting .gallery-photo { cursor:pointer; }
    .gallery-photos.is-selecting .gallery-photo-check { display:grid; }
    .gallery-photo-actions { position:absolute; z-index:2; right:9px; bottom:9px; display:flex; gap:6px; opacity:.68; transition:opacity .18s ease; }
    .gallery-photo-tile:hover .gallery-photo-actions, .gallery-photo-tile:focus-within .gallery-photo-actions { opacity:1; }
    .gallery-photos.is-selecting .gallery-photo-actions { display:none; }
    .gallery-photo-actions form { margin:0; }
    .gallery-photo-actions form[data-selection-kind="favourite"] { order:1; }
    .gallery-photo-actions form[data-selection-kind="cart"] { order:2; }
    .gallery-photo-actions .gallery-icon-button { width:37px; height:34px; border-color:#ffffff9c; border-radius:7px; background:#102a3bd9; color:#fff; backdrop-filter:blur(4px); }
    .gallery-photo-actions .gallery-icon-button:hover, .gallery-photo-actions .gallery-icon-button:focus-visible { border-color:#fff; background:#087eae; color:#fff; }
    .gallery-photo-actions .gallery-icon-button.is-selected { border-color:#79d4f6; background:#078bc4; color:#fff; }
    .gallery-photo-actions .gallery-icon-button.is-favourite { background:#385fbedf; color:#fff; }
    .gallery-photo-actions .gallery-icon-button svg { width:18px; height:18px; }
    .gallery-icon-button { position:relative; }
    .gallery-icon-button:disabled { cursor:not-allowed; opacity:.42; }
    .gallery-icon-button[data-tooltip]:hover::after, .gallery-icon-button[data-tooltip]:focus-visible::after { position:absolute; z-index:5; right:0; bottom:calc(100% + 7px); border-radius:6px; background:#10212c; color:#fff; content:attr(data-tooltip); font-size:12px; font-weight:700; line-height:1.2; padding:7px 9px; white-space:nowrap; box-shadow:0 4px 13px #0004; }
    .gallery-product-actions .gallery-icon-button[data-tooltip]:hover::after, .gallery-product-actions .gallery-icon-button[data-tooltip]:focus-visible::after { right:auto; left:50%; transform:translateX(-50%); }
    .gallery-selection-bar { display:flex; flex-wrap:wrap; align-items:center; gap:12px; margin:0 0 16px; padding:13px 16px; border:1px solid #80c5e1; border-radius:10px; background:#e7f7fe; }
    .gallery-selection-bar[hidden] { display:none; }
    .gallery-selection-bar strong { margin-right:auto; }
    .gallery-selection-bar .button { min-height:38px; padding:7px 13px; }
    .gallery-selection-bar button[type="button"] { border:0; background:transparent; color:#285068; font:inherit; font-weight:750; cursor:pointer; }
    .gallery-feedback { position:fixed; z-index:1001; right:20px; bottom:20px; max-width:min(350px,calc(100% - 40px)); padding:12px 16px; border-radius:9px; background:#10212c; color:#fff; box-shadow:0 8px 30px #0005; font-weight:700; }
    .gallery-photo-viewer .gallery-feedback { position:absolute; right:16px; bottom:16px; }
    .gallery-feedback[hidden] { display:none; }
    .gallery-photo-viewer { position: fixed; inset: 0; width: min(1100px, calc(100% - 24px)); max-height: calc(100% - 24px); padding: 0; border: 0; border-radius: 12px; background: #101820; color: #fff; box-shadow: 0 30px 90px #0008; }
    .gallery-photo-viewer::backdrop { background: #000c; }
    .gallery-viewer-inner { display: flex; flex-direction: column; max-height: calc(100vh - 24px); }
    .gallery-viewer-bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 16px; }
    .gallery-viewer-bar p { margin: 0; font-weight: 750; }
    .gallery-viewer-photo-frame { position:relative; align-self:center; width:fit-content; max-width:100%; margin:auto; }
    .gallery-viewer-actions { display:flex; align-items:center; gap:7px; margin-left:0; }
    .gallery-viewer-photo-frame:hover .gallery-viewer-actions, .gallery-viewer-photo-frame:focus-within .gallery-viewer-actions { opacity:1; }
    .gallery-viewer-actions form { margin:0; }
    .gallery-viewer-actions .gallery-icon-button { background:#24323a; border-color:#52636c; color:#fff; }
    .gallery-viewer-actions .gallery-icon-button.is-selected { border-color:#79d4f6; background:#078bc4; color:#fff; }
    .gallery-viewer-actions .gallery-icon-button.is-favourite { background:#385fbe; }
    .gallery-viewer-close { border: 1px solid #52636c; border-radius: 7px; background: #24323a; color: #fff; cursor: pointer; font: inherit; font-weight: 750; padding: 8px 12px; }
    .gallery-viewer-image { display: block; max-width: 100%; max-height: calc(100vh - 150px); width: auto; height: auto; object-fit: contain; }
    .gallery-viewer-controls { display: flex; justify-content: space-between; gap: 12px; padding: 12px 16px; }
    .gallery-viewer-controls button { min-height: 40px; border: 1px solid #52636c; border-radius: 7px; background: #24323a; color: #fff; cursor: pointer; font: inherit; font-weight: 700; padding: 8px 12px; }
    .gallery-viewer-controls button:disabled { opacity: .4; cursor: default; }
    @media (hover:none) { .gallery-photo-actions { opacity:1; } }
    @media (max-width: 650px) { .gallery-products { grid-template-columns:repeat(auto-fit,minmax(min(100%,220px),1fr)); } .gallery-photos { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 9px; } .gallery-individual-heading { display:block; } .gallery-viewer-bar { flex-wrap:wrap; } }
</style>
@endpush

@section('content')
@if($adminPreview ?? false)
<div style="background:#e6f6fd;border-bottom:1px solid #b2dcec;padding:12px 0"><div class="container" style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap"><span><strong>Customer preview</strong> · This is the live customer gallery layout. Shopping controls are shown but disabled.</span><a class="button secondary" href="{{ route('admin.competition-gallery.show', $event) }}">Back to management</a></div></div>
<style>.gallery-product-actions,.gallery-photo-actions,.competition-tabs .competition-tab:not([aria-current="page"]){pointer-events:none}.gallery-product-actions,.gallery-photo-actions{opacity:.42!important}</style>
@endif
@php
    $competitionRoutes = $competitionRoutes ?? [
        'home' => 'customer.media.index', 'show' => 'customer.competitions.show',
        'image' => 'customer.competitions.image', 'selection' => 'customer.competitions.selections.store',
        'batch' => 'customer.competitions.selections.photos.store',
    ];
    $competitionHomeUrl = $competitionHomeUrl ?? route('customer.media.index');
    $mediaPricing = app(\App\Features\Media\Services\MediaPriceLists::class);
    $pricingItemUuid = ($selected['kind'] ?? null) === 'item' ? $selected['uuid'] : null;
    $photoPrice = $mediaPricing->competitionPrice($event, 'competition_photo', $pricingItemUuid);
    $photoPriceText = '$'.number_format($photoPrice / 100, 2);
@endphp
<section class="hero"><div class="container">@include('customers._heading', ['headingImageUrl' => $event->logoUrl() ?? $event->crewLogoUrl(), 'eyebrow' => $eventCode ?: 'Competition media', 'heading' => $title, 'description' => 'Browse your event’s released photos and videos.'])</div></section>
<section class="section" id="gallery-content"><div class="container">
    @include('customers.competitions.partials.tabs')
    @if(session('status'))<p role="status">{{ session('status') }}</p>@endif
    @if($errors->any())<p class="error" role="alert">{{ $errors->first() }}</p>@endif
    @php
        $galleryBreadcrumbs = [['label' => $title, 'url' => route($competitionRoutes['show'], $event).'#gallery-content']];
        foreach ($breadcrumbs as $crumb) $galleryBreadcrumbs[] = ['label' => $crumb['label'], 'url' => route($competitionRoutes['show'], ['schedulingEvent' => $event, 'folder' => $crumb['uuid']]).'#gallery-content'];
    @endphp
    <x-gallery-breadcrumbs :items="$galleryBreadcrumbs" />
    @if(!$selected)<h2>Browse the event</h2>@elseif($selected['kind'] !== 'item')<h2>{{ $selected['label'] }}</h2>@endif
    @if($children)
        <div class="gallery-navigation-grid">
            @foreach($children as $child)
                <x-gallery-navigation-card class="gallery-folder" :title="$child['label']" :image="($child['cover_uuid'] ?? null) ? route($competitionRoutes['image'], ['schedulingEvent' => $event, 'photo' => $child['cover_uuid'], 'size' => 'cover']) : null" :subtitle="$child['kind'] === 'item' ? 'Routine' : ($child['kind'] === 'day' ? 'Day' : 'Section')" :href="route($competitionRoutes['show'], ['schedulingEvent' => $event, 'folder' => $child['uuid']]).'#gallery-content'" :action="'View '.($child['kind'] === 'item' ? 'routine' : strtolower($child['kind']))" />
            @endforeach
        </div>
    @elseif($photos && $photos->isNotEmpty())
        <svg aria-hidden="true" style="position:absolute;width:0;height:0;overflow:hidden" xmlns="http://www.w3.org/2000/svg">
            <symbol id="gallery-single-photo" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m4 18 5-5 3 3 3-4 5 5"/></symbol>
            <symbol id="gallery-camera" viewBox="0 0 24 24"><path d="M3 7h4l2-3h6l2 3h4v13H3zM12 10a4 4 0 1 0 0 8 4 4 0 0 0 0-8Z"/></symbol>
            <symbol id="gallery-package" viewBox="0 0 24 24"><rect x="3" y="7" width="18" height="14" rx="2"/><path d="M3 10h18M12 7v14M8 7c-4-2-3-6 0-6 2 0 4 3 4 6m4 0c4-2 3-6 0-6-2 0-4 3-4 6"/></symbol>
            <symbol id="gallery-video" viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m10 8 6 4-6 4z"/></symbol>
            <symbol id="gallery-cart" viewBox="0 0 24 24"><path d="M3 4h2l2.1 11h11.5l2-8H6"/><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></symbol>
            <symbol id="gallery-heart" viewBox="0 0 24 24"><path d="M20.8 8.5c0 5-8.8 11-8.8 11s-8.8-6-8.8-11a4.6 4.6 0 0 1 8.8-1.8 4.6 4.6 0 0 1 8.8 1.8Z"/></symbol>
        </svg>
        @php
            $products = [
                ['product' => 'individual_photos', 'icon' => 'single-photo', 'title' => 'Individual photos', 'price' => $photoPrice, 'note' => 'Choose your favourite shots'],
                ['product' => 'routine_photos', 'icon' => 'camera', 'title' => 'All photos from this routine', 'price' => $mediaPricing->competitionPrice($event, 'competition_routine_photos', $selected['uuid']), 'note' => 'Every photo in this routine'],
                ['product' => 'routine_package', 'icon' => 'package', 'title' => 'Photos + video package', 'price' => $packagePrice, 'note' => $videoAvailable ? 'Photos and the routine video' : 'Video included when ready'],
            ];
            $products[] = ['product' => 'routine_video', 'icon' => 'video', 'title' => 'Individual video from this routine', 'price' => $mediaPricing->competitionPrice($event, 'competition_routine_video', $selected['uuid']), 'note' => $videoAvailable ? 'The full routine video' : 'Available when the video is ready'];
        @endphp
        <div class="gallery-products" aria-label="Routine products">
            @foreach($products as $product)
                @php($selection = $product['product'] === 'individual_photos' ? null : $shopping['selections']->first(fn ($item) => $item->item_uuid === $selected['uuid'] && $item->product === $product['product']))
                @php($ownedProduct = $product['product'] === 'routine_photos' ? isset($shopping['owned']['routine_photos'][$selected['uuid']]) : ($product['product'] === 'routine_video' ? isset($shopping['owned']['routine_videos'][$selected['uuid']]) : ($product['product'] === 'routine_package' ? isset($shopping['owned']['packages'][$selected['uuid']]) : false)))
                <article class="card gallery-product">
                    <span class="gallery-product-symbol" aria-hidden="true"><svg class="gallery-icon-button-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"><use href="#gallery-{{ $product['icon'] }}"/></svg></span>
                    <h3>{{ $product['title'] }}</h3>
                    <p class="gallery-product-price">${{ number_format($product['price'] / 100) }}@if($product['product'] === 'individual_photos') <span style="font-size:14px;font-weight:700">each</span>@endif</p>
                    <p class="gallery-product-note">{{ $product['note'] }}</p>
                    @if($ownedProduct)<p class="gallery-product-note"><strong>✓ Already owned</strong> · available in My Media</p>@endif
                    <div class="gallery-product-actions">
                        @if($product['product'] === 'individual_photos')
                            <a class="gallery-icon-button" href="#gallery-photos" data-start-selection="add" data-tooltip="Add to cart · {{ $photoPriceText }} each" aria-label="Choose individual photos to add to cart for {{ $photoPriceText }} each"><svg viewBox="0 0 24 24"><use href="#gallery-cart"/></svg></a>
                            <a class="gallery-icon-button" href="#gallery-photos" data-start-selection="favourite" data-tooltip="Add to favourites" aria-label="Choose individual photos to add to favourites"><svg viewBox="0 0 24 24"><use href="#gallery-heart"/></svg></a>
                        @else
                        <form method="post" action="{{ route($competitionRoutes['selection'], $event) }}" data-selection-form data-selection-kind="cart" data-selection-label="{{ $product['title'] }}" data-selection-price="${{ number_format($product['price'] / 100) }}">@csrf
                            <input type="hidden" name="item_uuid" value="{{ $selected['uuid'] }}"><input type="hidden" name="product" value="{{ $product['product'] }}">
                            <input type="hidden" name="action" value="{{ $selection?->in_cart ? 'remove' : 'add' }}">
                            <button class="gallery-icon-button {{ $selection?->in_cart ? 'is-selected' : '' }}" type="submit" data-tooltip="{{ $ownedProduct ? 'Already owned' : ($product['product'] === 'routine_video' && !$videoAvailable ? 'Available when video is ready' : ($selection?->in_cart ? 'Remove from cart' : 'Add to cart · $'.number_format($product['price'] / 100))) }}" aria-label="{{ $ownedProduct ? $product['title'].' already owned' : ($product['product'] === 'routine_video' && !$videoAvailable ? 'Video available for cart when ready' : ($selection?->in_cart ? 'Remove '.$product['title'].' from cart' : 'Add '.$product['title'].' to cart')) }}" aria-pressed="{{ $selection?->in_cart ? 'true' : 'false' }}" @disabled($ownedProduct || ($product['product'] === 'routine_video' && !$videoAvailable))><svg viewBox="0 0 24 24"><use href="#gallery-cart"/></svg></button>
                        </form>
                        <form method="post" action="{{ route($competitionRoutes['selection'], $event) }}" data-selection-form data-selection-kind="favourite" data-selection-label="{{ $product['title'] }}">@csrf
                            <input type="hidden" name="item_uuid" value="{{ $selected['uuid'] }}"><input type="hidden" name="product" value="{{ $product['product'] }}">
                            <input type="hidden" name="action" value="{{ $selection?->is_favourite ? 'unfavourite' : 'favourite' }}">
                            <button class="gallery-icon-button {{ $selection?->is_favourite ? 'is-favourite' : '' }}" type="submit" data-tooltip="{{ $selection?->is_favourite ? 'Remove from favourites' : 'Add to favourites' }}" aria-label="{{ $selection?->is_favourite ? 'Remove '.$product['title'].' from favourites' : 'Add '.$product['title'].' to favourites' }}" aria-pressed="{{ $selection?->is_favourite ? 'true' : 'false' }}"><svg viewBox="0 0 24 24"><use href="#gallery-heart"/></svg></button>
                        </form>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
        <div class="gallery-individual-heading"><h2>Choose your photos</h2><p>Use the icons on a photo for one, or the Individual photos card above to select several.</p></div>
        <form class="gallery-selection-bar" id="gallery-selection-bar" method="post" action="{{ route($competitionRoutes['batch'], $event) }}" hidden>@csrf
            <input type="hidden" name="item_uuid" value="{{ $selected['uuid'] }}"><input type="hidden" name="action" id="gallery-selection-action" value="add">
            <strong id="gallery-selection-count" role="status">0 photos selected on this page</strong>
            <button class="button" type="submit" id="gallery-selection-submit">Add selected to cart</button>
            <button type="button" id="gallery-selection-cancel">Cancel</button>
            <span id="gallery-selection-fields"></span>
        </form>
        <div class="gallery-photos" id="gallery-photos">
            @foreach($photos as $photo)
                @php($previewUrl = route($competitionRoutes['image'], ['schedulingEvent' => $event, 'photo' => $photo->uuid]))
                @php($photoFavourite = $shopping['selections']->first(fn ($selection) => $selection->photo_uuid === $photo->uuid && $selection->is_favourite))
                @php($photoInCart = $shopping['selections']->first(fn ($selection) => $selection->photo_uuid === $photo->uuid && $selection->in_cart))
                @php($photoOwned = isset($shopping['owned']['routine_photos'][$selected['uuid']]) || isset($shopping['owned']['keys']['photo:'.$photo->uuid]))
                <div class="gallery-photo-tile" data-photo-uuid="{{ $photo->uuid }}">
                    <div class="gallery-photo-frame">
                        <span class="gallery-photo-check" aria-hidden="true">✓</span>
                        <a class="card gallery-photo" href="{{ $previewUrl }}" target="_blank" rel="noopener" data-preview="{{ $previewUrl }}" data-cart="{{ $photoInCart ? 'true' : 'false' }}" data-favourite="{{ $photoFavourite ? 'true' : 'false' }}" aria-label="View watermarked photo from {{ $selected['label'] }}">
                            <img src="{{ route($competitionRoutes['image'], ['schedulingEvent' => $event, 'photo' => $photo->uuid, 'size' => 'thumbnail']) }}" alt="Photo from {{ $selected['label'] }}" loading="lazy">
                        </a>
                        <div class="gallery-photo-actions">
                            <form method="post" action="{{ route($competitionRoutes['selection'], $event) }}" data-selection-form data-selection-kind="cart" data-selection-label="this photo" data-selection-price="{{ $photoPriceText }}">@csrf
                                <input type="hidden" name="item_uuid" value="{{ $selected['uuid'] }}"><input type="hidden" name="photo_uuid" value="{{ $photo->uuid }}"><input type="hidden" name="product" value="photo"><input type="hidden" name="action" value="{{ $photoInCart ? 'remove' : 'add' }}">
                                <button class="gallery-icon-button {{ $photoInCart ? 'is-selected' : '' }}" type="submit" data-tooltip="{{ $photoOwned ? 'Already owned' : ($photoInCart ? 'Remove from cart' : 'Add to cart · '.$photoPriceText) }}" aria-label="{{ $photoOwned ? 'This photo is already owned' : ($photoInCart ? 'Remove this photo from cart' : 'Add this photo to cart for '.$photoPriceText) }}" aria-pressed="{{ $photoInCart ? 'true' : 'false' }}" @disabled($photoOwned)><svg viewBox="0 0 24 24"><use href="#gallery-cart"/></svg></button>
                            </form>
                            <form method="post" action="{{ route($competitionRoutes['selection'], $event) }}" data-selection-form data-selection-kind="favourite" data-selection-label="this photo">@csrf
                                <input type="hidden" name="item_uuid" value="{{ $selected['uuid'] }}"><input type="hidden" name="photo_uuid" value="{{ $photo->uuid }}"><input type="hidden" name="product" value="photo"><input type="hidden" name="action" value="{{ $photoFavourite ? 'unfavourite' : 'favourite' }}">
                                <button class="gallery-icon-button {{ $photoFavourite ? 'is-favourite' : '' }}" type="submit" data-tooltip="{{ $photoFavourite ? 'Remove from favourites' : 'Add to favourites' }}" aria-label="{{ $photoFavourite ? 'Remove this photo from favourites' : 'Add this photo to favourites' }}" aria-pressed="{{ $photoFavourite ? 'true' : 'false' }}"><svg viewBox="0 0 24 24"><use href="#gallery-heart"/></svg></button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <nav aria-label="Photo pages" class="actions">
            @if($photos->previousPageUrl())<a class="button secondary" href="{{ $photos->previousPageUrl() }}">← Previous photos</a>@endif
            @if($photos->nextPageUrl())<a class="button secondary" href="{{ $photos->nextPageUrl() }}">Next photos →</a>@endif
        </nav>
        <dialog class="gallery-photo-viewer" id="gallery-photo-viewer" aria-label="Photo preview">
            <div class="gallery-viewer-inner">
                <div class="gallery-viewer-bar">
                    <p>Watermarked preview</p>
                    <button class="gallery-viewer-close" type="button" data-close>Close ✕</button>
                </div>
                <div class="gallery-viewer-photo-frame">
                    <img class="gallery-viewer-image" id="gallery-viewer-image" alt="Watermarked photo preview">
                    <div class="gallery-photo-actions gallery-viewer-actions">
                        <form method="post" action="{{ route($competitionRoutes['selection'], $event) }}" data-selection-form data-selection-kind="cart" data-selection-label="this photo" data-selection-price="{{ $photoPriceText }}">@csrf
                            <input type="hidden" name="item_uuid" value="{{ $selected['uuid'] }}"><input type="hidden" name="photo_uuid" data-viewer-photo><input type="hidden" name="product" value="photo"><input type="hidden" name="action" data-viewer-action value="add">
                            <button class="gallery-icon-button" type="submit" data-viewer-cart data-tooltip="Add to cart · {{ $photoPriceText }}" aria-label="Add this photo to cart for {{ $photoPriceText }}"><svg viewBox="0 0 24 24"><use href="#gallery-cart"/></svg></button>
                        </form>
                        <form method="post" action="{{ route($competitionRoutes['selection'], $event) }}" data-selection-form data-selection-kind="favourite" data-selection-label="this photo">@csrf
                            <input type="hidden" name="item_uuid" value="{{ $selected['uuid'] }}"><input type="hidden" name="photo_uuid" data-viewer-photo><input type="hidden" name="product" value="photo"><input type="hidden" name="action" data-viewer-action value="favourite">
                            <button class="gallery-icon-button" type="submit" data-viewer-favourite data-tooltip="Add to favourites" aria-label="Add this photo to favourites"><svg viewBox="0 0 24 24"><use href="#gallery-heart"/></svg></button>
                        </form>
                    </div>
                </div>
                <div class="gallery-viewer-controls"><button type="button" data-previous>← Previous photo</button><button type="button" data-next>Next photo →</button></div>
            </div>
            <div class="gallery-feedback" id="gallery-viewer-feedback" role="status" aria-live="polite" hidden></div>
        </dialog>
        <div class="gallery-feedback" id="gallery-feedback" role="status" aria-live="polite" hidden></div>
    @else
        <div class="empty"><h2>Photos are being prepared</h2><p style="margin-bottom:0">Please check back soon.</p></div>
    @endif
</div></section>
@endsection

@push('scripts')
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
(() => {
    const grid = document.getElementById('gallery-photos');
    const viewer = document.getElementById('gallery-photo-viewer');
    if (!grid) return;
    const links = [...grid.querySelectorAll('[data-preview]')];
    const bar = document.getElementById('gallery-selection-bar');
    const actionField = document.getElementById('gallery-selection-action');
    const count = document.getElementById('gallery-selection-count');
    const submit = document.getElementById('gallery-selection-submit');
    const fields = document.getElementById('gallery-selection-fields');
    const feedback = document.getElementById('gallery-feedback');
    const viewerFeedback = document.getElementById('gallery-viewer-feedback');
    const selectionForms = [...document.querySelectorAll('[data-selection-form]')];
    let feedbackTimer;
    const announce = message => {
        const target = viewer?.open ? viewerFeedback : feedback;
        feedback.hidden = true;
        viewerFeedback.hidden = true;
        target.textContent = message;
        target.hidden = false;
        clearTimeout(feedbackTimer);
        feedbackTimer = setTimeout(() => { target.hidden = true; }, 4000);
    };
    const syncSelection = data => {
        selectionForms.forEach(form => {
            if (form.elements.product.value !== data.product || (form.elements.photo_uuid?.value || null) !== data.photo_uuid) return;
            const cart = form.dataset.selectionKind === 'cart';
            const active = cart ? data.in_cart : data.is_favourite;
            const button = form.querySelector('button');
            form.elements.action.value = cart ? (active ? 'remove' : 'add') : (active ? 'unfavourite' : 'favourite');
            button.classList.toggle(cart ? 'is-selected' : 'is-favourite', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
            button.dataset.tooltip = cart
                ? (active ? 'Remove from cart' : `Add to cart · ${form.dataset.selectionPrice}`)
                : (active ? 'Remove from favourites' : 'Add to favourites');
            button.setAttribute('aria-label', `${active ? 'Remove' : 'Add'} ${form.dataset.selectionLabel} ${active ? 'from' : 'to'} ${cart ? 'cart' : 'favourites'}${!active && cart ? ` for ${form.dataset.selectionPrice}` : ''}`);
        });
        if (data.photo_uuid) {
            const link = links.find(candidate => candidate.closest('[data-photo-uuid]').dataset.photoUuid === data.photo_uuid);
            if (link) {
                link.dataset.cart = data.in_cart ? 'true' : 'false';
                link.dataset.favourite = data.is_favourite ? 'true' : 'false';
            }
        }
        const cartTab = document.querySelector('[data-cart-tab]');
        if (cartTab) {
            let badge = cartTab.querySelector('.competition-tab-count');
            if (data.cart_count > 0) {
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'competition-tab-count';
                    cartTab.append(badge);
                }
                badge.textContent = data.cart_count;
            } else badge?.remove();
        }
        const favouritesTab = document.querySelector('[data-favourites-tab]');
        if (favouritesTab) {
            let badge = favouritesTab.querySelector('.competition-tab-count');
            if (data.favourite_count > 0) {
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'competition-tab-count';
                    favouritesTab.append(badge);
                }
                badge.textContent = data.favourite_count;
            } else badge?.remove();
        }
    };
    selectionForms.forEach(form => form.addEventListener('submit', async event => {
        event.preventDefault();
        if (form.dataset.saving === 'true') return;
        form.dataset.saving = 'true';
        const button = form.querySelector('button');
        button.disabled = true;
        try {
            const response = await fetch(form.getAttribute('action'), {
                method: 'POST', body: new FormData(form), credentials: 'same-origin',
                headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
            });
            const result = await response.json().catch(() => null);
            if (!response.ok || !result?.success) throw new Error(result?.message || 'Could not save your choice. Please refresh and try again.');
            syncSelection(result.data);
            announce(result.message);
        } catch (error) {
            announce(error.message || 'Could not save your choice. Please try again.');
        } finally {
            button.disabled = false;
            delete form.dataset.saving;
        }
    }));
    const picked = new Set();
    let selectionMode = null;
    const updateSelection = () => {
        grid.classList.toggle('is-selecting', selectionMode !== null);
        bar.hidden = selectionMode === null;
        if (selectionMode === null) return;
        actionField.value = selectionMode;
        count.textContent = `${picked.size} ${picked.size === 1 ? 'photo' : 'photos'} selected on this page`;
        submit.textContent = selectionMode === 'add' ? 'Add selected to cart' : 'Save selected to favourites';
        submit.disabled = picked.size === 0;
        fields.replaceChildren(...[...picked].map(uuid => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'photo_uuids[]';
            input.value = uuid;
            return input;
        }));
        grid.querySelectorAll('[data-photo-uuid]').forEach(tile => {
            const selected = picked.has(tile.dataset.photoUuid);
            tile.classList.toggle('is-picked', selected);
            const link = tile.querySelector('[data-preview]');
            link.setAttribute('role', 'checkbox');
            link.setAttribute('aria-checked', selected ? 'true' : 'false');
            link.setAttribute('aria-label', `${selected ? 'Deselect' : 'Select'} photo for ${selectionMode === 'add' ? 'cart' : 'favourites'}`);
        });
    };
    const togglePhoto = uuid => {
        if (picked.has(uuid)) picked.delete(uuid);
        else picked.add(uuid);
        updateSelection();
    };
    const startSelection = action => {
        selectionMode = action;
        picked.clear();
        updateSelection();
        bar.scrollIntoView({block:'nearest', behavior:'smooth'});
    };
    document.querySelectorAll('[data-start-selection]').forEach(link => link.addEventListener('click', event => {
        event.preventDefault();
        startSelection(link.dataset.startSelection);
    }));
    document.getElementById('gallery-selection-cancel').addEventListener('click', () => {
        selectionMode = null;
        picked.clear();
        grid.querySelectorAll('[data-photo-uuid]').forEach(tile => {
            tile.classList.remove('is-picked');
            const link = tile.querySelector('[data-preview]');
            link.removeAttribute('role');
            link.removeAttribute('aria-checked');
            link.setAttribute('aria-label', link.dataset.previewLabel);
        });
        updateSelection();
    });
    links.forEach(link => { link.dataset.previewLabel = link.getAttribute('aria-label'); });
    if (!viewer || typeof viewer.showModal !== 'function') return;
    const image = document.getElementById('gallery-viewer-image');
    const previous = viewer.querySelector('[data-previous]');
    const next = viewer.querySelector('[data-next]');
    let current = 0;
    const show = index => {
        current = index;
        const link = links[index];
        image.src = link.dataset.preview;
        const photoUuid = link.closest('[data-photo-uuid]').dataset.photoUuid;
        viewer.querySelectorAll('[data-viewer-photo]').forEach(input => { input.value = photoUuid; });
        const cartButton = viewer.querySelector('[data-viewer-cart]');
        const favouriteButton = viewer.querySelector('[data-viewer-favourite]');
        const inCart = link.dataset.cart === 'true';
        const isFavourite = link.dataset.favourite === 'true';
        cartButton.classList.toggle('is-selected', inCart);
        cartButton.setAttribute('aria-pressed', inCart ? 'true' : 'false');
        cartButton.setAttribute('aria-label', inCart ? 'Remove this photo from cart' : 'Add this photo to cart for {{ $photoPriceText }}');
        cartButton.dataset.tooltip = inCart ? 'Remove from cart · {{ $photoPriceText }} photo' : 'Add to cart · {{ $photoPriceText }}';
        cartButton.closest('form').querySelector('[data-viewer-action]').value = inCart ? 'remove' : 'add';
        favouriteButton.classList.toggle('is-favourite', isFavourite);
        favouriteButton.setAttribute('aria-pressed', isFavourite ? 'true' : 'false');
        favouriteButton.setAttribute('aria-label', isFavourite ? 'Remove this photo from favourites' : 'Save this photo to favourites');
        favouriteButton.dataset.tooltip = isFavourite ? 'Remove from favourites' : 'Add to favourites';
        favouriteButton.closest('form').querySelector('[data-viewer-action]').value = isFavourite ? 'unfavourite' : 'favourite';
        previous.disabled = index === 0;
        next.disabled = index === links.length - 1;
    };
    links.forEach((link, index) => link.addEventListener('click', event => {
        event.preventDefault();
        if (selectionMode !== null) {
            togglePhoto(link.closest('[data-photo-uuid]').dataset.photoUuid);
            return;
        }
        show(index);
        viewer.showModal();
    }));
    const focusPhoto = @json($focusPhoto);
    const focusIndex = links.findIndex(link => link.closest('[data-photo-uuid]').dataset.photoUuid === focusPhoto);
    if (focusIndex >= 0) {
        show(focusIndex);
        viewer.showModal();
    }
    previous.addEventListener('click', () => { if (current > 0) show(current - 1); });
    next.addEventListener('click', () => { if (current < links.length - 1) show(current + 1); });
    viewer.querySelector('[data-close]').addEventListener('click', () => viewer.close());
    viewer.addEventListener('close', () => { image.removeAttribute('src'); links[current]?.focus(); });
    viewer.addEventListener('click', event => { if (event.target === viewer) viewer.close(); });
    viewer.addEventListener('keydown', event => {
        if (event.key === 'Escape') { event.preventDefault(); viewer.close(); return; }
        if (event.key === 'ArrowLeft' && current > 0) { event.preventDefault(); show(current - 1); }
        if (event.key === 'ArrowRight' && current < links.length - 1) { event.preventDefault(); show(current + 1); }
    });
})();
</script>
@endpush
