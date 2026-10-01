@extends('layouts.public', ['title' => 'Event invoices · '.$studio->name, 'footerLabel' => 'Studio Hub'])
@include('customers.studios._styles')
@section('content')
@include('studio-invoices._styles')
@include('customers.studios._header')
@include('customers.studios._tabs', ['tab' => 'invoices'])
<section class="section"><div class="container">
    @include('customers.studios._booking-actions')
    <div class="section-head"><div><p class="eyebrow">Bookings</p><h2 style="margin-bottom:0">Event invoices</h2></div><span class="meta">Your estimates, invoices and payments</span></div>
    @if($estimates->isNotEmpty())
        <section aria-label="Quote estimates and minimum spends" style="margin-bottom:32px">
            <h3>Quote estimates &amp; minimum spends</h3>
            @foreach($estimates as $estimate)
                <details class="card hub-card" style="margin-bottom:16px">
                    <summary class="hub-card-header"><div><span class="hub-badge">{{ Str::headline($estimate['booking']->status->value) }}</span><h3 style="margin:8px 0 0">@foreach($estimate['receipt']['details']['items'] as $item){{ $loop->first ? '' : ' · ' }}{{ $item['title'] ?: ($item['item_type'] === 'dress_rehearsal' ? 'DR Portrait' : 'Concert') }}@endforeach</h3><p class="meta" style="margin:4px 0 0">@foreach($estimate['receipt']['details']['items'] as $item){{ $loop->first ? '' : ' · ' }}{{ \Carbon\CarbonImmutable::parse($item['event_date'])->format('j M Y') }}@endforeach</p></div></summary>
                    <div class="hub-card-content">
                        @include('public.concert-bookings._quote', ['quote' => $estimate['receipt']['quote']])
                        <a class="button secondary" href="{{ route('customer.studio-bookings.show', [$studio, $estimate['booking']]) }}">My timeline</a>
                    </div>
                </details>
            @endforeach
        </section>
    @endif
    <h3>Issued invoices</h3>
    @forelse($invoices as $invoice)
        @php($overdue = $invoice->status === 'issued' && $invoice->balanceCents() > 0 && $invoice->due_date?->copy()->endOfDay()->isPast())
        <article class="card card-body" style="margin-bottom:16px">
            <div class="studio-billing-heading"><div><h3>Invoice {{ $invoice->number() }}</h3><p class="meta">{{ $invoice->is_test ? 'TEST · ' : '' }}{{ $invoice->paymentLabel() }}@if(!$invoice->isPaid() && $invoice->due_date) · <span class="check-due {{ $overdue ? 'overdue' : '' }}">{{ $overdue ? 'Overdue · ' : 'Due ' }}{{ $invoice->due_date->format('j M Y') }}</span>@endif</p></div>@if($invoice->isPaid())<div class="studio-invoice-paid" role="status"><strong>✓ Invoice paid</strong><span>Paid in full · {{ $invoice->payments->where('status', 'succeeded')->max('paid_at')?->format('j M Y') }}</span></div>@else<strong>${{ number_format($invoice->balanceCents()/100,2) }} outstanding</strong>@endif</div>
            <div class="hub-actions"><a class="button" href="{{ route('customer.studio-invoices.show', [$studio, $invoice]) }}">View invoice</a><a class="button secondary" href="{{ route('customer.studio-invoices.pdf', [$studio, $invoice]) }}">Download PDF</a></div>
        </article>
    @empty
        <div class="empty-compact"><h3>No invoices yet</h3><p class="muted">Issued invoices will appear here for you to view, download and pay.</p></div>
    @endforelse
    {{ $invoices->links() }}
</div></section>
@endsection
