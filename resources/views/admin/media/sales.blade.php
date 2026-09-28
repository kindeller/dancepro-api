@extends('layouts.admin', ['title' => 'Sales reports', 'heading' => 'Sales reports', 'subheading' => 'Sales, customer engagement, discounts and download activity'])

@section('content')
@php
    $money = fn (int $amount): string => '$'.number_format($amount / 100, 2);
    $query = collect($filters)->except('tab')->filter(fn ($value) => $value !== null && $value !== '')->all();
    $summary = $results['summary'];
    $previous = $results['previousSummary'];
    $delta = function (string $key) use ($summary, $previous): ?float {
        if (! $previous || $previous[$key] == 0) return null;
        return (($summary[$key] - $previous[$key]) / $previous[$key]) * 100;
    };
@endphp
@include('admin.partials.report-tabs-styles')
<style>
    .report-filters { display:grid; grid-template-columns:repeat(5,minmax(130px,1fr)); gap:12px; align-items:end; }
    .report-filters label { display:block; font-size:13px; font-weight:800; }
    .report-filters input,.report-filters select { width:100%; margin-top:5px; }
    .report-actions { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
    .report-metrics { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:12px; margin:18px 0; }
    .report-metric { padding:18px; border:1px solid var(--line); border-radius:12px; background:var(--panel); box-shadow:var(--shadow); }
    .report-metric span { display:block; color:var(--muted); font-size:13px; font-weight:800; }
    .report-metric strong { display:block; margin-top:6px; font-size:26px; line-height:1.1; }
    .report-delta { margin-top:7px; font-size:12px; color:var(--muted); }
    .report-delta.up { color:#087a55; }.report-delta.down { color:#b42333; }
    .report-table-wrap { overflow-x:auto; }
    .report-empty { padding:28px; text-align:center; color:var(--muted); }
    .report-filter-panel { margin-bottom:20px; }
    .report-filter-panel summary { cursor:pointer; font-weight:800; color:var(--brand-strong); }
    .report-filter-panel[open] summary { margin-bottom:14px; }
    .report-shortcuts { display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:18px; }
    .loyalty-off { border-left:5px solid #e0a400; }
    .event-studio-row td { background:#edf7fb; font-weight:700; }
    .event-studio-row td:first-child { border-left:4px solid var(--brand); }
    .event-child-row td { background:#fbfdfe; }
    .event-child-name { display:flex; gap:9px; align-items:flex-start; padding-left:18px; }
    .event-child-marker { color:var(--brand); font-weight:900; }
    @media(max-width:1000px){.report-filters{grid-template-columns:repeat(2,minmax(0,1fr))}.report-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:800px){.report-tab-groups{gap:10px}.report-tab-group-label{display:none}}
    @media(max-width:600px){.report-filters,.report-metrics{grid-template-columns:1fr}}
</style>

@php
    $tabGroups = [
        'Performance' => ['overview' => 'Overview', 'customers' => 'Customer performance', 'products' => 'Product performance', 'events' => 'Event engagement'],
        'Operations' => ['discounts' => 'Discounts & credit', 'downloads' => 'Downloads'],

    ];
@endphp
<p><a class="button secondary" href="{{ route('admin.orders.index') }}">Orders &amp; refunds</a></p>
<nav class="report-tab-groups" aria-label="Sales reports">
    @foreach($tabGroups as $group => $tabs)
        <div class="report-tab-group"><span class="report-tab-group-label">{{ $group }}</span><div class="report-tabs" role="tablist">
            @foreach($tabs as $tab => $label)
                <a href="{{ route('admin.media-sales.index', ['tab' => $tab] + $query) }}" role="tab" aria-selected="{{ $activeTab === $tab ? 'true' : 'false' }}">{{ $label }}</a>
            @endforeach
        </div></div>
    @endforeach
</nav>

<details class="card card-pad report-filter-panel" @if(collect($filters)->except('tab')->filter()->isNotEmpty()) open @endif>
    <summary>Filter this report @if(collect($filters)->except('tab')->filter()->isNotEmpty())<span class="badge">Active</span>@endif</summary>
    <form method="GET" action="{{ route('admin.media-sales.index') }}" class="report-filters">
        <input type="hidden" name="tab" value="{{ $activeTab }}">
        <label>From<input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></label>
        <label>To<input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></label>
        @if($activeTab !== 'downloads')<label>Media type<select name="kind"><option value="">All media</option><option value="competition" @selected(($filters['kind'] ?? '') === 'competition')>Competition</option><option value="concert" @selected(($filters['kind'] ?? '') === 'concert')>Concert</option></select></label>@endif
        <label>Customer or order<input type="search" name="customer" value="{{ $filters['customer'] ?? '' }}" placeholder="Name, email or order number"></label>
        <div class="report-actions"><button type="submit">Apply filters</button><a class="button secondary" href="{{ route('admin.media-sales.index', ['tab' => $activeTab]) }}">Clear</a></div>
    </form>
</details>

@if($activeTab === 'overview')
    <div class="report-metrics">
        @foreach(['sales' => ['Cash collected', $money($summary['sales'])], 'orders' => ['Paid orders', number_format($summary['orders'])], 'customers' => ['Customers', number_format($summary['customers'])], 'average_order' => ['Average order', $money($summary['average_order'])], 'median_order' => ['Median order', $money($summary['median_order'])]] as $key => [$label, $value])
            @php($change = $delta($key))
            <div class="report-metric"><span>{{ $label }}</span><strong>{{ $value }}</strong>@if($change !== null)<div class="report-delta {{ $change >= 0 ? 'up' : 'down' }}">{{ $change >= 0 ? '▲' : '▼' }} {{ number_format(abs($change), 1) }}% from previous matching period</div>@endif</div>
        @endforeach
    </div>
    <div class="grid two-col">
        <section class="card card-pad"><h2>Payment breakdown</h2><p>Gross before discounts <strong>{{ $money($summary['gross']) }}</strong></p><p class="muted">Dates select orders by when they were paid. Refunds include all completed refunds for those orders.</p><p>Completed refunds <strong>{{ $money($summary['refunds']) }}</strong> · Net receipts <strong>{{ $money($summary['net_receipts']) }}</strong></p><p><strong>{{ $money($summary['sales']) }}</strong> cash collected</p><p class="muted">Included GST: {{ $money($summary['tax']) }} · Discounts: {{ $money($summary['discounts']) }} · Account credit used: {{ $money($summary['credit']) }}</p></section>
        <section class="card card-pad"><h2>Items sold</h2><p><strong style="font-size:26px">{{ number_format($summary['items']) }}</strong></p><p class="muted">Across {{ number_format($summary['orders']) }} paid customer orders, including any later refunded.</p></section>
    </div>
@elseif($activeTab === 'customers')
    <div class="report-shortcuts"><a class="button" href="{{ route('admin.customers.index') }}">Open customer accounts</a><span class="muted">Search an account, review activity or perform customer support actions.</span></div>
    <div class="report-metrics">
        <div class="report-metric"><span>Customers</span><strong>{{ number_format($summary['customers']) }}</strong></div>
        <div class="report-metric"><span>New customers</span><strong>{{ $results['customers']['classification_available'] ? number_format($results['customers']['new']) : '—' }}</strong></div>
        <div class="report-metric"><span>Returning customers</span><strong>{{ $results['customers']['classification_available'] ? number_format($results['customers']['returning']) : '—' }}</strong></div>
        <div class="report-metric"><span>Multiple orders in period</span><strong>{{ number_format($results['customers']['repeat']) }}</strong></div>
        <div class="report-metric"><span>Repeat-customer sales</span><strong>{{ $money($results['customers']['repeat_sales']) }}</strong></div>
    </div>
    @unless($results['customers']['classification_available'])<p class="notice">Choose a From date to separate new and returning customers.</p>@endunless
    <section class="card card-pad"><h2>Highest-value customers in this period</h2><div class="report-table-wrap"><table><thead><tr><th>Customer</th><th>Orders</th><th>Cash collected</th><th>Last order</th></tr></thead><tbody>@forelse($results['customers']['top'] as $customer)<tr><td>@if($customer['user_id'])<a href="{{ route('admin.customers.show', $customer['user_id']) }}"><strong>{{ $customer['name'] }}</strong></a>@else<strong>{{ $customer['name'] }}</strong>@endif<br><span class="muted">{{ $customer['email'] }}</span></td><td>{{ $customer['orders'] }}</td><td>{{ $money($customer['sales']) }}</td><td>{{ $customer['last_order']?->format('j M Y') }}</td></tr>@empty<tr><td colspan="4" class="report-empty">No paid customers match these filters.</td></tr>@endforelse</tbody></table></div></section>
@elseif($activeTab === 'products')
    <section class="card card-pad"><h2>Product performance</h2><div class="report-table-wrap"><table><thead><tr><th>Product</th><th>Quantity</th><th>Orders</th><th>Cash collected</th></tr></thead><tbody>@forelse($results['products'] as $product)<tr><td><strong>{{ $product['label'] }}</strong><br><span class="muted">{{ $product['key'] }}</span></td><td>{{ number_format($product['quantity']) }}</td><td>{{ number_format($product['orders']) }}</td><td>{{ $money($product['sales']) }}</td></tr>@empty<tr><td colspan="4" class="report-empty">No product sales match these filters.</td></tr>@endforelse</tbody></table></div></section>
@elseif($activeTab === 'events')
    @php($eventSummary = $results['events']['summary'])
    <div class="report-metrics">
        <div class="report-metric"><span>Gallery visitors</span><strong>{{ number_format($eventSummary['visitors']) }}</strong></div>
        <div class="report-metric"><span>With favourites</span><strong>{{ number_format($eventSummary['with_favourites']) }}</strong></div>
        <div class="report-metric"><span>With cart items</span><strong>{{ number_format($eventSummary['with_cart']) }}</strong></div>
        <div class="report-metric"><span>With paid orders</span><strong>{{ number_format($eventSummary['with_orders']) }}</strong></div>
        <div class="report-metric"><span>Without paid orders</span><strong>{{ number_format($eventSummary['without_orders']) }}</strong></div>
    </div>
    <section class="card card-pad">
        <div style="margin-bottom:14px"><h2>Event engagement</h2><p class="muted" style="margin:6px 0 0">Competition and concert event names open the backend gallery. Studio rows combine all of their concert events. View activity shows the customers and guests who accessed them.</p></div>
        <div class="report-table-wrap"><table style="min-width:1050px"><thead><tr><th>Event</th><th>Type</th><th>Visitors</th><th>With favourites</th><th>With cart items</th><th>With orders</th><th>Without orders</th><th>Orders</th><th>Cash collected</th><th></th></tr></thead><tbody>
        @forelse($results['events']['rows'] as $event)
            <tr @class(['event-studio-row' => $event['row_type'] === 'studio', 'event-child-row' => $event['row_type'] === 'concert_event'])>
                <td>
                    @if($event['row_type'] === 'concert_event')<div class="event-child-name"><span class="event-child-marker">↳</span><div>@endif
                    @if($event['row_type'] === 'studio')<strong>{{ $event['name'] }}</strong>@else<a href="{{ $event['gallery_url'] }}"><strong>{{ $event['name'] }}</strong></a>@endif
                    @if($event['row_type'] === 'studio')
                        <br><span class="muted">All {{ $event['event_count'] }} concert {{ Str::plural('event', $event['event_count']) }}</span>
                    @elseif($event['event_date'])
                        <br><span class="muted">{{ $event['event_date']->format('j M Y') }}</span>
                    @endif
                    @if($event['row_type'] === 'concert_event')</div></div>@endif
                </td>
                <td>{{ match($event['row_type']) { 'studio' => 'Concert studio', 'concert_event' => 'Concert event', default => 'Competition' } }}</td>
                <td>{{ number_format($event['visitors']) }}</td>
                <td>{{ number_format($event['with_favourites']) }}</td>
                <td>{{ number_format($event['with_cart']) }}</td>
                <td>{{ number_format($event['with_orders']) }}</td>
                <td>{{ number_format($event['without_orders']) }}</td>
                <td>{{ number_format($event['orders']) }}</td>
                <td>{{ $money($event['sales']) }}</td>
                <td><a href="{{ $event['activity_url'] }}"><strong>View activity</strong></a></td>
            </tr>
        @empty
            <tr><td colspan="10" class="report-empty">No gallery events match these filters.</td></tr>
        @endforelse
        </tbody></table></div>
    </section>
@elseif($activeTab === 'discounts')
    <div class="report-shortcuts"><a class="button secondary" href="{{ route('admin.media-settings.index', ['tab' => 'discounts']) }}">Manage discounts and credit</a></div>
    <div class="report-metrics">
        <div class="report-metric"><span>Bulk discounts</span><strong>{{ $money($results['discounts']['bulk']) }}</strong></div>
        <div class="report-metric"><span>Discount codes</span><strong>{{ $money($results['discounts']['codes']) }}</strong><div class="report-delta">{{ $results['discounts']['code_orders'] }} orders</div></div>
        <div class="report-metric"><span>Credit used</span><strong>{{ $money($results['discounts']['credit_used']) }}</strong><div class="report-delta">{{ $results['discounts']['credit_orders'] }} orders</div></div>
        <div class="report-metric"><span>Current outstanding credit</span><strong>{{ $money($results['credit']['balance']) }}</strong><div class="report-delta">{{ $results['credit']['customers'] }} customer accounts</div></div>
        <div class="report-metric"><span>Lifetime credit activity</span><strong>{{ $money($results['credit']['granted']) }}</strong><div class="report-delta">Granted · {{ $money($results['credit']['used']) }} used</div></div>
    </div>
    <section class="card card-pad"><h2>How totals are separated</h2><p>Bulk discounts, codes and account credit remain separate values. Account credit is applied after discounts and reduces the amount on which included GST is calculated.</p></section>
@elseif($activeTab === 'downloads')
    <div class="report-metrics">
        <div class="report-metric"><span>Customer/file records</span><strong>{{ number_format($downloadSummary['files']) }}</strong></div>
        <div class="report-metric"><span>Lifetime requests</span><strong>{{ number_format($downloadSummary['requests']) }}</strong></div>
        <div class="report-metric"><span>Records with repeat requests</span><strong>{{ number_format($downloadSummary['repeat_files']) }}</strong></div>
        <div class="report-metric"><span>Estimated transfer</span><strong>{{ number_format($downloadSummary['bytes'] / 1073741824, 2) }} GB</strong></div>
    </div>
    <section class="card card-pad"><h2>Customer download activity</h2><p class="muted">Dates filter by the last request for each customer/file record. Counts and estimated transfer are lifetime totals for those matching records, not transfers within the date range. A request records a download handoff, not confirmed delivery.</p><div class="report-table-wrap"><table><thead><tr><th>Customer</th><th>File</th><th>Type</th><th>Downloads</th><th>Estimated transfer</th><th>Last requested</th></tr></thead><tbody>@forelse($downloads as $download)<tr><td><strong>{{ $download->user?->name }}</strong><br><span class="muted">{{ $download->user?->email }}</span></td><td>{{ $download->asset?->display_name ?: $download->asset?->original_filename }}</td><td>{{ ucfirst($download->asset?->media_type?->value ?? 'media') }}</td><td>{{ number_format($download->download_count) }}</td><td>{{ number_format($download->bytes_requested / 1048576, 1) }} MB</td><td>{{ $download->last_download_at?->format('j M Y, g:i a') }}</td></tr>@empty<tr><td colspan="6" class="report-empty">No purchased-media downloads match these filters.</td></tr>@endforelse</tbody></table></div>{{ $downloads?->links() }}</section>
@elseif($activeTab === 'loyalty')
    <section class="card card-pad loyalty-off"><span class="badge attention">Not enabled</span><h2 style="margin-top:12px">Loyalty reporting is ready for a future program</h2><p>No loyalty balances or rewards are being created. When enabled, this report will separate pending, available, redeemed, expired, reversed and outstanding credit.</p><p class="muted">The preserved planning baseline proposes 5% credit, available after 30 days, a $5 redemption minimum, a 50% order cap and expiry after 24 months of inactivity. These remain candidate rules.</p><a class="button secondary" href="{{ route('admin.media-sales.export', $query) }}">Export current sales data</a></section>
@else
    <section class="card card-pad"><div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap"><h2>Paid orders</h2><a class="button secondary" href="{{ route('admin.media-sales.export', $query) }}">Export CSV</a></div><div class="report-table-wrap"><table><thead><tr><th>Order</th><th>Customer</th><th>Date</th><th>Type</th><th>Items</th><th>Paid</th></tr></thead><tbody>@forelse($orders as $order)<tr><td><a href="{{ route('admin.orders.show', $order) }}"><strong>{{ $order->displayNumber() }}</strong></a></td><td>@if($order->user)<a href="{{ route('admin.customers.show', $order->user) }}">{{ $order->customer_name }}</a>@else{{ $order->customer_name }}@endif<br><span class="muted">{{ $order->customer_email }}</span></td><td>{{ $order->paid_at?->format('j M Y') ?? 'Unavailable' }}</td><td>{{ ucfirst(data_get($order->metadata, 'kind', 'media')) }}</td><td>{{ $order->items->sum('quantity') }}</td><td>{{ $money($order->total_amount) }}<br><span class="muted">GST {{ $money($order->tax_amount) }}</span></td></tr>@empty<tr><td colspan="6" class="report-empty">No paid orders match these filters.</td></tr>@endforelse</tbody></table></div>{{ $orders->links() }}</section>
@endif
@endsection
