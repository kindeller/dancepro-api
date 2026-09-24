@extends('layouts.public', ['title' => 'Checkout · DancePro', 'footerLabel' => 'DancePro Media'])

@push('styles')
<style>
    .checkout-grid { display:grid; grid-template-columns:minmax(0,1fr) minmax(280px,380px); gap:24px; align-items:start; }
    .checkout-card { padding:22px; }
    .checkout-items { display:grid; gap:12px; margin-top:18px; }
    .checkout-item { display:flex; justify-content:space-between; gap:18px; padding-bottom:12px; border-bottom:1px solid #d7e1e6; }
    .test-banner { border:2px solid #0b8abd; background:#e6f7fd; padding:16px; border-radius:10px; font-weight:750; }
    .test-actions { display:grid; gap:10px; margin-top:18px; }
    .test-actions form { margin:0; }
    .test-actions button { width:100%; }
    .download-list { display:grid; gap:8px; margin-top:12px; }
    @media(max-width:760px){.checkout-grid{grid-template-columns:1fr}}
</style>
@endpush

@section('content')
<div class="container" style="padding-top:18px"><a href="{{ route('customer.media.index') }}">← My Media</a></div>
<section class="hero" style="padding:38px 0"><div class="container"><h1>{{ $order->status->value === 'draft' ? 'Review your order' : 'Order complete' }}</h1><p class="lead">Order {{ $order->uuid }}</p></div></section>
<section class="section"><div class="container">
    @if(session('status'))<p class="test-banner" role="status">{{ session('status') }}</p>@endif
    @if($errors->any())<div class="card checkout-card" role="alert"><strong>Payment was not completed.</strong><p>{{ $errors->first() }}</p></div>@endif
    <div class="checkout-grid">
        <div class="card checkout-card"><h2>Media</h2><div class="checkout-items">
            @foreach($order->items as $item)<div class="checkout-item"><div><strong>{{ $item->snapshot_display_name }}</strong><p class="meta">{{ ucfirst($item->item_type) }}@if($item->metadata['media_pending'] ?? false) · supplied when ready @endif</p></div><strong>${{ number_format($item->total_price_amount / 100, 2) }}</strong></div>@endforeach
        </div>
        @if($order->metadata['discount_code'] ?? null)<p style="display:flex;justify-content:space-between"><span>Code {{ $order->metadata['discount_code'] }} @if(($order->metadata['discount_code_type'] ?? 'percent') === 'percent')({{ $order->metadata['discount_code_percent'] }}%)@endif</span><strong>−${{ number_format($order->metadata['discount_code_amount'] / 100, 2) }}</strong></p>@endif
        @if(($order->metadata['bulk_discount_amount'] ?? 0) > 0)<p style="display:flex;justify-content:space-between"><span>Bulk discount</span><strong>−${{ number_format($order->metadata['bulk_discount_amount'] / 100, 2) }}</strong></p>@endif
        @if(($order->metadata['account_credit_amount'] ?? 0) > 0)
            <p style="display:flex;justify-content:space-between"><span>Account credit</span><strong>−${{ number_format($order->metadata['account_credit_amount'] / 100, 2) }}</strong></p>
        @endif
        <p style="display:flex;justify-content:space-between;font-size:20px"><strong>Total including GST</strong><strong>${{ number_format($order->total_amount / 100, 2) }} AUD</strong></p>
        <p class="meta" style="display:flex;justify-content:space-between"><span>Included GST</span><span>${{ number_format($order->tax_amount / 100, 2) }}</span></p></div>
        <aside class="card checkout-card">
            @if($order->status->value === 'draft')
                @if($testMode)<div class="test-banner">Test checkout<br><span style="font-weight:500">No money will be charged and no payment details are collected.</span></div>
                <div class="test-actions">
                    <form method="post" action="{{ route('customer.checkout.pay', $order) }}">@csrf<input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}"><input type="hidden" name="outcome" value="approved"><button class="button" type="submit">Approve test payment</button></form>
                    <form method="post" action="{{ route('customer.checkout.pay', $order) }}">@csrf<input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}"><input type="hidden" name="outcome" value="declined"><button class="button secondary" type="submit">Try a declined payment</button></form>
                </div>@else<div class="test-banner">Checkout is currently unavailable.</div>@endif
            @else
                <h2>Downloads</h2>
                @php($readyCount = 0)
                <div class="download-list">@foreach($order->items as $item)@foreach($item->mediaEntitlements as $entitlement)
                    @if(!$entitlement->ready_at)<p><strong>{{ $item->snapshot_display_name }}</strong><br><span class="meta">Preparing — its download clock has not started.</span></p>
                    @elseif($entitlement->expires_at?->isFuture())@foreach($entitlement->assets as $asset)@php($readyCount++)<a class="button secondary" href="{{ route('customer.purchases.download', [$entitlement, $asset]) }}">Download {{ $asset->display_name ?: $asset->original_filename }}</a>@endforeach
                    @else<p>{{ $item->snapshot_display_name }}: download period ended.</p>@endif
                @endforeach @endforeach</div>
                @if($readyCount === 0)<p>Your ready files will appear here.</p>@endif
                <p><a href="{{ route('customer.media.orders.index') }}">View all orders →</a></p>
            @endif
        </aside>
    </div>
</div></section>
@endsection
