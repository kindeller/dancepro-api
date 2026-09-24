@extends('layouts.public', ['title' => $studio->name.' photo cart', 'footerLabel' => 'Concert photos'])

@push('styles')
<style>
    .studio-cart-list { display:grid; gap:10px; max-width:800px; }
    .studio-cart-row { display:flex; align-items:center; gap:12px; padding:12px; }
    .studio-cart-row img { width:94px; max-height:94px; object-fit:contain; background:#14212a; }
    .studio-cart-row .details { flex:1; }
    .studio-cart-row p { margin:3px 0; }
    .studio-cart-row button { border:1px solid #ccd9df; border-radius:7px; background:#fff; padding:8px 11px; cursor:pointer; font:inherit; }
    .studio-cart-total { max-width:800px; padding:20px; margin-top:20px; }
    .studio-cart-total p { display:flex; justify-content:space-between; gap:12px; margin:7px 0; }
    .studio-cart-total .total { border-top:1px solid #ccd9df; padding-top:12px; font-size:20px; font-weight:800; }
</style>
@endpush

@section('content')
@php
    $concertRoutes = $concertRoutes ?? ['image' => 'customer.concerts.photos.image', 'selection' => 'customer.concerts.photos.selections.store'];
    $concertBackUrl = $concertBackUrl ?? route('customer.profile.studio', $studio);
@endphp
<div class="container" style="padding-top:18px"><a href="{{ $concertBackUrl }}">← {{ $studio->name }} concerts</a></div>
<section class="hero" style="padding:38px 0"><div class="container"><h1>Favourites &amp; photo cart</h1><p class="lead">{{ $studio->name }}</p></div></section>
<section class="section"><div class="container">
    @if(session('status'))<p role="status">{{ session('status') }}</p>@endif
    @if($errors->any())<p class="error" role="alert">{{ $errors->first() }}</p>@endif
    <h2>Saved favourites</h2>
    <div class="studio-cart-list">
        @forelse($selections->where('is_favourite', true) as $selection)
            <div class="card studio-cart-row"><img src="{{ route($concertRoutes['image'], ['concert' => $selection->concert, 'asset' => $selection->asset->uuid, 'size' => 'thumbnail']) }}" alt="Watermarked concert photo">
                <div class="details"><strong>{{ $selection->concert->name }}</strong><p class="meta">Saved photo</p></div>
                <form method="post" action="{{ route($concertRoutes['selection'], $selection->concert) }}">@csrf
                    <input type="hidden" name="asset_uuid" value="{{ $selection->asset->uuid }}"><input type="hidden" name="action" value="unfavourite"><button type="submit">Remove</button>
                </form>
            </div>
        @empty<p>No concert photo favourites saved for this studio.</p>@endforelse
    </div>
    <h2 style="margin-top:36px">Your cart</h2>
    <div class="studio-cart-list">
        @forelse($cart as $selection)
            <div class="card studio-cart-row"><img src="{{ route($concertRoutes['image'], ['concert' => $selection->concert, 'asset' => $selection->asset->uuid, 'size' => 'thumbnail']) }}" alt="Watermarked concert photo">
                <div class="details">
                    <strong>{{ $selection->concert->name }}</strong>
                    <p class="meta">
                        Photo · ${{ number_format(($lineAmounts[$selection->id] ?? $photoPrice) / 100, 2) }}
                        @if($principalPrice)
                            · Studio principal price
                        @endif
                    </p>
                </div>
                <form method="post" action="{{ route($concertRoutes['selection'], $selection->concert) }}">@csrf
                    <input type="hidden" name="asset_uuid" value="{{ $selection->asset->uuid }}"><input type="hidden" name="action" value="remove"><button type="submit">Remove</button>
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
</div></section>
@endsection
