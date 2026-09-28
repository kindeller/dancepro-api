@extends('layouts.admin', [
    'title' => 'Gallery visitors · '.$event['name'],
    'heading' => 'Gallery visitors',
    'subheading' => $event['name'].' · '.$event['scope_label'],
])

@section('content')
@php
    $money = fn (int $amount): string => '$'.number_format($amount / 100, 2);
    $exportQuery = collect($filters)->except(['page', 'per_page'])->filter(fn ($value) => $value !== null && $value !== '')->all();
@endphp
<style>
    .visitor-toolbar { display:flex; justify-content:space-between; gap:12px; align-items:center; flex-wrap:wrap; margin-bottom:18px; }
    .visitor-actions { display:flex; gap:8px; flex-wrap:wrap; }
    .visitor-metrics { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:12px; margin-bottom:18px; }
    .visitor-metric { padding:16px; border:1px solid var(--line); border-radius:8px; background:var(--panel); box-shadow:var(--shadow); }
    .visitor-metric span { display:block; color:var(--muted); font-size:12px; font-weight:800; }
    .visitor-metric strong { display:block; margin-top:5px; font-size:25px; line-height:1.1; }
    .visitor-filters { display:grid; grid-template-columns:minmax(220px,2fr) repeat(3,minmax(135px,1fr)) 100px auto; gap:10px; align-items:end; }
    .visitor-filters label { display:block; font-size:12px; font-weight:800; }
    .visitor-filters input,.visitor-filters select { width:100%; margin-top:5px; }
    .visitor-table { min-width:1080px; }
    .visitor-name { min-width:230px; }
    .visitor-orders { min-width:210px; }
    .visitor-orders a { white-space:nowrap; }
    .visitor-number { text-align:center; }
    .visitor-access { display:inline-flex; padding:3px 8px; border-radius:999px; background:var(--soft); color:var(--brand-strong); font-size:11px; font-weight:800; }
    .report-empty { padding:28px; text-align:center; color:var(--muted); }
    @media(max-width:1100px){.visitor-metrics{grid-template-columns:repeat(3,minmax(0,1fr))}.visitor-filters{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:620px){.visitor-metrics,.visitor-filters{grid-template-columns:1fr}}
.visitor-table th:first-child,.visitor-table td:first-child{text-align:left}</style>

<div class="visitor-toolbar">
    <div class="visitor-actions">
        <a class="button secondary" href="{{ route('admin.media-sales.index', ['tab' => 'events']) }}">← Back to events</a>
        <a class="button" href="{{ $event['gallery_url'] }}">{{ $event['open_label'] }}</a>
    </div>
    <a class="button secondary" href="{{ $event['export_url'].($exportQuery ? '?'.http_build_query($exportQuery) : '') }}">Export visitors CSV</a>
</div>

<div class="visitor-metrics">
    <div class="visitor-metric"><span>Gallery visitors</span><strong>{{ number_format($summary['visitors']) }}</strong></div>
    <div class="visitor-metric"><span>With favourites</span><strong>{{ number_format($summary['with_favourites']) }}</strong></div>
    <div class="visitor-metric"><span>With cart items</span><strong>{{ number_format($summary['with_cart']) }}</strong></div>
    <div class="visitor-metric"><span>With paid orders</span><strong>{{ number_format($summary['with_orders']) }}</strong></div>
    <div class="visitor-metric"><span>Without paid orders</span><strong>{{ number_format($summary['without_orders']) }}</strong></div>
</div>

<section class="card card-pad" style="margin-bottom:18px">
    <form method="GET" action="{{ $event['activity_url'] }}" class="visitor-filters">
        <label>Visitor or order<input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Name, email, account ID or order"></label>
        <label>Activity<select name="activity">
            @foreach(['all' => 'All visitors', 'favourites' => 'With favourites', 'cart' => 'With cart items', 'ordered' => 'With paid orders', 'no_order' => 'Without paid orders', 'complimentary' => 'With complimentary media'] as $value => $label)
                <option value="{{ $value }}" @selected(($filters['activity'] ?? 'all') === $value)>{{ $label }}</option>
            @endforeach
        </select></label>
        <label>From<input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></label>
        <label>To<input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></label>
        <label>Rows<select name="per_page">@foreach([25,50,100] as $count)<option value="{{ $count }}" @selected((int)($filters['per_page'] ?? 50) === $count)>{{ $count }}</option>@endforeach</select></label>
        <div class="visitor-actions"><button type="submit">Apply filters</button><a class="button secondary" href="{{ $event['activity_url'] }}">Clear filters</a></div>
    </form>
</section>

<section class="card card-pad">
    <div style="display:flex;justify-content:space-between;gap:12px;align-items:end;flex-wrap:wrap;margin-bottom:14px">
        <div><h2>{{ $event['name'] }}</h2><p class="muted" style="margin:5px 0 0">{{ $event['context_label'] }}@if($event['event_date']) · {{ $event['event_date']->format('j M Y') }}@endif</p></div>
        <p class="muted" style="margin:0">{{ number_format($summary['orders']) }} paid orders · {{ $money($summary['sales']) }}</p>
    </div>
    <div style="overflow-x:auto">
        <table class="visitor-table">
            <thead><tr><th>Visitor</th><th>Access</th><th>First activity</th><th>Last activity</th><th>Views</th><th>Favourites</th><th>Cart items</th><th>Complimentary</th><th>Orders</th></tr></thead>
            <tbody>
            @forelse($visitors as $visitor)
                <tr>
                    <td class="visitor-name">
                        @if($visitor['user'])<a href="{{ route('admin.customers.show', $visitor['user']) }}"><strong>{{ $visitor['name'] }}</strong></a>@else<strong>{{ $visitor['name'] }}</strong>@endif
                        <br><span class="muted">{{ $visitor['email'] }}</span>
                        @if($visitor['user'])<br><span class="muted">Account #{{ $visitor['user']->id }}</span>@endif
                    </td>
                    <td><span class="visitor-access">{{ $visitor['access'] }}</span></td>
                    <td>{{ $visitor['first_activity']?->format('j M Y, g:i a') ?? '—' }}</td>
                    <td>{{ $visitor['last_activity']?->format('j M Y, g:i a') ?? '—' }}</td>
                    <td class="visitor-number">{{ number_format($visitor['views']) }}</td>
                    <td class="visitor-number">@if($visitor['user'] && $visitor['favourites'])<a href="{{ route('admin.customers.show', [$visitor['user'], 'tab'=>'favourites', 'event'=>$event['model'] instanceof \App\Features\Studios\Models\Studio ? null : $event['uuid']]) }}">{{ number_format($visitor['favourites']) }}</a>@else{{ $visitor['favourites'] ?: '—' }}@endif</td>
                    <td class="visitor-number">@if($visitor['user'] && $visitor['cart_items'])<a href="{{ route('admin.customers.show', [$visitor['user'], 'tab'=>'cart', 'event'=>$event['model'] instanceof \App\Features\Studios\Models\Studio ? null : $event['uuid']]) }}">{{ number_format($visitor['cart_items']) }}</a>@else{{ $visitor['cart_items'] ?: '—' }}@endif</td>
                    <td class="visitor-number">{{ $visitor['complimentary'] ? number_format($visitor['complimentary']) : '—' }}</td>
                    <td class="visitor-orders">
                        @forelse($visitor['orders'] as $order)
                            <a href="{{ route('admin.orders.show', $order['model']) }}">Order {{ $order['model']->displayNumber() }}</a>
                            <span class="muted">· {{ ucfirst($order['model']->status->value) }} · {{ $money($order['amount']) }}</span>@unless($loop->last)<br>@endunless
                        @empty
                            <span class="muted">No paid order</span>
                        @endforelse
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="report-empty">No gallery visitors match these filters.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $visitors->links() }}
</section>
@endsection
