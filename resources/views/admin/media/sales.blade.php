@extends('layouts.admin', ['title' => 'Media sales', 'heading' => 'Media sales', 'subheading' => 'Orders, customers and commerce performance'])

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
<style>
    .report-tabs { display:flex; gap:4px; margin:22px 0; overflow-x:auto; border-bottom:1px solid var(--line); }
    .report-tabs a { flex:0 0 auto; padding:12px 16px 11px; border:1px solid transparent; border-bottom:0; border-radius:8px 8px 0 0; color:var(--muted); font-weight:800; text-decoration:none; }
    .report-tabs a:hover { background:var(--soft); color:var(--brand-strong); text-decoration:none; }
    .report-tabs a[aria-selected="true"] { margin-bottom:-1px; border-color:var(--line); border-bottom-color:var(--panel); background:var(--panel); color:var(--ink); }
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
    .loyalty-off { border-left:5px solid #e0a400; }
    @media(max-width:1000px){.report-filters{grid-template-columns:repeat(2,minmax(0,1fr))}.report-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:600px){.report-filters,.report-metrics{grid-template-columns:1fr}}
</style>

<section class="card card-pad">
    <form method="GET" action="{{ route('admin.media-sales.index') }}" class="report-filters">
        <input type="hidden" name="tab" value="{{ $activeTab }}">
        <label>From<input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></label>
        <label>To<input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></label>
        <label>Media type<select name="kind"><option value="">All media</option><option value="competition" @selected(($filters['kind'] ?? '') === 'competition')>Competition</option><option value="concert" @selected(($filters['kind'] ?? '') === 'concert')>Concert</option></select></label>
        <label>Customer<input type="search" name="customer" value="{{ $filters['customer'] ?? '' }}" placeholder="Name or email"></label>
        <div class="report-actions"><button type="submit">Apply filters</button><a class="button secondary" href="{{ route('admin.media-sales.index', ['tab' => $activeTab]) }}">Clear</a></div>
    </form>
</section>

<nav class="report-tabs" aria-label="Sales reports" role="tablist">
    @foreach(['overview' => 'Overview', 'customers' => 'Customers', 'products' => 'Products', 'events' => 'Events', 'discounts' => 'Discounts & credit', 'loyalty' => 'Loyalty', 'orders' => 'Orders'] as $tab => $label)
        <a href="{{ route('admin.media-sales.index', ['tab' => $tab] + $query) }}" role="tab" aria-selected="{{ $activeTab === $tab ? 'true' : 'false' }}">{{ $label }}</a>
    @endforeach
</nav>

@if($activeTab === 'overview')
    <div class="report-metrics">
        @foreach(['sales' => ['Revenue', $money($summary['sales'])], 'orders' => ['Paid orders', number_format($summary['orders'])], 'customers' => ['Customers', number_format($summary['customers'])], 'average_order' => ['Average order', $money($summary['average_order'])], 'median_order' => ['Median order', $money($summary['median_order'])]] as $key => [$label, $value])
            @php($change = $delta($key))
            <div class="report-metric"><span>{{ $label }}</span><strong>{{ $value }}</strong>@if($change !== null)<div class="report-delta {{ $change >= 0 ? 'up' : 'down' }}">{{ $change >= 0 ? '▲' : '▼' }} {{ number_format(abs($change), 1) }}% from previous matching period</div>@endif</div>
        @endforeach
    </div>
    <div class="grid two-col">
        <section class="card card-pad"><h2>Payment breakdown</h2><p><strong>{{ $money($summary['sales']) }}</strong> paid total</p><p class="muted">Included GST: {{ $money($summary['tax']) }} · Discounts: {{ $money($summary['discounts']) }} · Account credit used: {{ $money($summary['credit']) }}</p></section>
        <section class="card card-pad"><h2>Items sold</h2><p><strong style="font-size:26px">{{ number_format($summary['items']) }}</strong></p><p class="muted">Across {{ number_format($summary['orders']) }} paid or fulfilled customer orders.</p></section>
    </div>
@elseif($activeTab === 'customers')
    <div class="report-metrics">
        <div class="report-metric"><span>Customers</span><strong>{{ number_format($summary['customers']) }}</strong></div>
        <div class="report-metric"><span>New customers</span><strong>{{ $results['customers']['classification_available'] ? number_format($results['customers']['new']) : '—' }}</strong></div>
        <div class="report-metric"><span>Returning customers</span><strong>{{ $results['customers']['classification_available'] ? number_format($results['customers']['returning']) : '—' }}</strong></div>
        <div class="report-metric"><span>Multiple orders in period</span><strong>{{ number_format($results['customers']['repeat']) }}</strong></div>
        <div class="report-metric"><span>Repeat-customer sales</span><strong>{{ $money($results['customers']['repeat_sales']) }}</strong></div>
    </div>
    @unless($results['customers']['classification_available'])<p class="notice">Choose a From date to separate new and returning customers.</p>@endunless
    <section class="card card-pad"><h2>Highest-value customers in this period</h2><div class="report-table-wrap"><table><thead><tr><th>Customer</th><th>Orders</th><th>Sales</th><th>Last order</th></tr></thead><tbody>@forelse($results['customers']['top'] as $customer)<tr><td><strong>{{ $customer['name'] }}</strong><br><span class="muted">{{ $customer['email'] }}</span></td><td>{{ $customer['orders'] }}</td><td>{{ $money($customer['sales']) }}</td><td>{{ $customer['last_order']?->format('j M Y') }}</td></tr>@empty<tr><td colspan="4" class="report-empty">No paid customers match these filters.</td></tr>@endforelse</tbody></table></div></section>
@elseif($activeTab === 'products')
    <section class="card card-pad"><h2>Product performance</h2><div class="report-table-wrap"><table><thead><tr><th>Product</th><th>Quantity</th><th>Orders</th><th>Sales</th></tr></thead><tbody>@forelse($results['products'] as $product)<tr><td><strong>{{ $product['label'] }}</strong><br><span class="muted">{{ $product['key'] }}</span></td><td>{{ number_format($product['quantity']) }}</td><td>{{ number_format($product['orders']) }}</td><td>{{ $money($product['sales']) }}</td></tr>@empty<tr><td colspan="4" class="report-empty">No product sales match these filters.</td></tr>@endforelse</tbody></table></div></section>
@elseif($activeTab === 'events')
    <section class="card card-pad"><h2>Event and studio performance</h2><div class="report-table-wrap"><table><thead><tr><th>Source</th><th>Type</th><th>Customers</th><th>Orders</th><th>Sales</th></tr></thead><tbody>@forelse($results['events'] as $event)<tr><td><strong>{{ $event['name'] }}</strong></td><td>{{ ucfirst($event['kind']) }}</td><td>{{ number_format($event['customers']) }}</td><td>{{ number_format($event['orders']) }}</td><td>{{ $money($event['sales']) }}</td></tr>@empty<tr><td colspan="5" class="report-empty">No event sales match these filters.</td></tr>@endforelse</tbody></table></div></section>
@elseif($activeTab === 'discounts')
    <div class="report-metrics">
        <div class="report-metric"><span>Bulk discounts</span><strong>{{ $money($results['discounts']['bulk']) }}</strong></div>
        <div class="report-metric"><span>Discount codes</span><strong>{{ $money($results['discounts']['codes']) }}</strong><div class="report-delta">{{ $results['discounts']['code_orders'] }} orders</div></div>
        <div class="report-metric"><span>Credit used</span><strong>{{ $money($results['discounts']['credit_used']) }}</strong><div class="report-delta">{{ $results['discounts']['credit_orders'] }} orders</div></div>
        <div class="report-metric"><span>Outstanding credit</span><strong>{{ $money($results['credit']['balance']) }}</strong><div class="report-delta">{{ $results['credit']['customers'] }} customer accounts</div></div>
        <div class="report-metric"><span>Credit ledger activity</span><strong>{{ $money($results['credit']['granted']) }}</strong><div class="report-delta">Granted · {{ $money($results['credit']['used']) }} used</div></div>
    </div>
    <section class="card card-pad"><h2>How totals are separated</h2><p>Bulk discounts, codes and account credit remain separate values. Account credit is applied after discounts and reduces the amount on which included GST is calculated.</p></section>
@elseif($activeTab === 'loyalty')
    <section class="card card-pad loyalty-off"><span class="badge attention">Not enabled</span><h2 style="margin-top:12px">Loyalty reporting is ready for a future program</h2><p>No loyalty balances or rewards are being created. When enabled, this report will separate pending, available, redeemed, expired, reversed and outstanding credit.</p><p class="muted">The preserved planning baseline proposes 5% credit, available after 30 days, a $5 redemption minimum, a 50% order cap and expiry after 24 months of inactivity. These remain candidate rules.</p><a class="button secondary" href="{{ route('admin.media-sales.export', $query) }}">Export current sales data</a></section>
@else
    <section class="card card-pad"><div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap"><h2>Paid orders</h2><a class="button secondary" href="{{ route('admin.media-sales.export', $query) }}">Export CSV</a></div><div class="report-table-wrap"><table><thead><tr><th>Order</th><th>Customer</th><th>Date</th><th>Type</th><th>Items</th><th>Paid</th></tr></thead><tbody>@forelse($orders as $order)<tr><td><strong>{{ $order->uuid }}</strong></td><td>{{ $order->customer_name }}<br><span class="muted">{{ $order->customer_email }}</span></td><td>{{ $order->paid_at?->format('j M Y') ?? 'Unavailable' }}</td><td>{{ ucfirst(data_get($order->metadata, 'kind', 'media')) }}</td><td>{{ $order->items_count }}</td><td>{{ $money($order->total_amount) }}<br><span class="muted">GST {{ $money($order->tax_amount) }}</span></td></tr>@empty<tr><td colspan="6" class="report-empty">No paid orders match these filters.</td></tr>@endforelse</tbody></table></div>{{ $orders->links() }}</section>
@endif
@endsection
