@extends('layouts.public', ['title' => 'My orders · DancePro', 'footerLabel' => 'DancePro Media'])
@section('headerParentUrl', route('customer.media.index'))

@push('styles')
<style>
    .order-list { display:grid; gap:16px; }
    .order-card { padding:20px; }
    .order-heading { display:flex; justify-content:space-between; flex-wrap:wrap; gap:12px; }
    .order-items { margin:14px 0 0; padding-left:20px; }
    .order-items li { margin:5px 0; }
</style>
@endpush

@section('content')
<section class="hero" style="padding:38px 0"><div class="container"><h1>My orders</h1><p class="lead">Your orders, complimentary media and download availability.</p></div></section>
<section class="section"><div class="container">
    @if($orders->count() === 0)<div class="empty"><p>You have no orders yet.</p></div>
    @else<div class="order-list">
        @foreach($orders as $order)
            <article class="card order-card">
                <div class="order-heading"><div><h2 style="font-size:24px;margin-bottom:4px">Order {{ $order->displayNumber() }}</h2>
                    <p class="meta">{{ $order->placed_at?->format('j M Y') ?? $order->created_at->format('j M Y') }} · {{ ucfirst($order->status->value) }}</p></div>
                    <div style="text-align:right"><strong>${{ number_format($order->total_amount / 100, 2) }} AUD</strong>@if(($order->metadata['account_credit_amount'] ?? 0) > 0)<div class="meta">After ${{ number_format($order->metadata['account_credit_amount'] / 100, 2) }} account credit</div>@endif</div></div>
                <ul class="order-items">
                    @foreach($order->items as $item)
                        @php($collection = $item->mediaCollection ?? $item->mediaAsset?->collection)
                        <li>{{ $item->snapshot_display_name ?: $collection?->name ?: $item->snapshot_filename ?: 'Media item' }}
                            @if($collection?->concert)<span class="meta">· {{ $collection->concert->name }}</span>@endif
                            @foreach($item->mediaEntitlements as $entitlement)
                                <span class="meta">· {{ ucfirst($entitlement->media_type) }}:
                                    @if(!$entitlement->ready_at)preparing
                                    @elseif($entitlement->expires_at?->isPast())download period ended {{ $entitlement->expires_at->format('j M Y') }} · <a href="{{ route('customer.media.help', ['type' => 'download_reactivation', 'order' => $order->id, 'entitlement' => $entitlement->id]) }}">Request reactivation</a>
                                    @else ready; downloads through {{ $entitlement->expires_at?->format('j M Y') }}
                                    @endif
                                </span>
                                @if($entitlement->ready_at && $entitlement->expires_at?->isFuture())
                                    @foreach($entitlement->assets as $asset)
                                        <a href="{{ route('customer.purchases.download', [$entitlement, $asset]) }}">Download {{ $asset->display_name ?: $asset->original_filename }}</a>
                                    @endforeach
                                @endif
                            @endforeach
                        </li>
                    @endforeach
                </ul>
                @if($order->status->value === 'pending')<p>Payment is pending. Media will appear after the order is paid and fulfilled.</p>
                @elseif($order->status->value === 'paid')<p>Payment received. Some media may still be preparing.</p>
                @else<p>Media is ready in My Media.</p>@endif
            </article>
        @endforeach
    </div>
    <nav class="actions" aria-label="Order pages">
        @if($orders->previousPageUrl())<a class="button secondary" href="{{ $orders->previousPageUrl() }}">← Previous orders</a>@endif
        @if($orders->nextPageUrl())<a class="button secondary" href="{{ $orders->nextPageUrl() }}">Next orders →</a>@endif
    </nav>@endif
</div></section>
@endsection
