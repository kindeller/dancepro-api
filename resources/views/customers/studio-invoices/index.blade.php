@extends('layouts.public', ['title' => $studio->name.' Studio Hub', 'footerLabel' => 'Studio Hub'])
@include('customers.studios._styles')
@section('content')
@include('studio-invoices._styles')
@include('customers.studios._header')
@include('customers.studios._tabs', ['tab' => 'invoices'])
<section class="section"><div class="container">
    <div class="hub-actions" style="justify-content:flex-end;margin:0 0 20px"><a class="button secondary" href="{{ route('customer.studio-bookings.index', $studio) }}">Event timeline</a><a class="button" href="{{ route('concert-bookings.create', ['studio' => $studio->uuid]) }}">Make booking</a></div>
    <div class="section-head"><div><p class="eyebrow">Bookings</p><h2 style="margin-bottom:0">Invoices</h2></div><span class="meta">Your estimates, invoices and payments</span></div>
    @if($estimates->isNotEmpty())
        <section aria-label="Quotes and minimum spends" style="margin-bottom:32px">
            <h3>Quotes &amp; minimum spends</h3>
            @foreach($estimates as $estimate)
                <details class="card hub-card" style="margin-bottom:16px" @if($loop->first) open @endif>
                    <summary class="hub-card-header"><div><span class="hub-badge">{{ Str::headline($estimate['booking']->status->value) }}</span><h3 style="margin:8px 0 0">@foreach($estimate['receipt']['details']['items'] as $item){{ $loop->first ? '' : ' · ' }}{{ $item['title'] ?: ($item['item_type'] === 'dress_rehearsal' ? 'DR Portrait' : 'Concert') }}@endforeach</h3><p class="meta" style="margin:4px 0 0">@foreach($estimate['receipt']['details']['items'] as $item){{ $loop->first ? '' : ' · ' }}{{ \Carbon\CarbonImmutable::parse($item['event_date'])->format('j M Y') }}@endforeach</p></div></summary>
                    <div class="hub-card-content">
                        @include('public.concert-bookings._quote', ['quote' => $estimate['receipt']['quote']])
                        <a href="{{ route('customer.studio-bookings.show', [$studio, $estimate['booking']]) }}">View event timeline →</a>
                    </div>
                </details>
            @endforeach
        </section>
    @endif
    <h3>Issued invoices</h3>
    @forelse($invoices as $invoice)
        @php($overdue = $invoice->status === 'issued' && $invoice->balanceCents() > 0 && $invoice->due_date?->copy()->endOfDay()->isPast())
        <article class="card card-body" style="margin-bottom:16px">
            <div class="studio-billing-heading"><div><h3>Invoice {{ $invoice->number() }}</h3><p class="meta">{{ $invoice->is_test ? 'TEST · ' : '' }}{{ $invoice->paymentLabel() }}@if($invoice->due_date) · <span class="check-due {{ $overdue ? 'overdue' : '' }}">{{ $overdue ? 'Overdue · ' : 'Due ' }}{{ $invoice->due_date->format('j M Y') }}</span>@endif</p></div><strong>${{ number_format($invoice->balanceCents()/100,2) }} outstanding</strong></div>
            <div class="hub-actions"><a class="button" href="{{ route('customer.studio-invoices.show', [$studio, $invoice]) }}">View invoice</a><a class="button secondary" href="{{ route('customer.studio-invoices.pdf', [$studio, $invoice]) }}">Download PDF</a></div>
        </article>
    @empty
        <div class="empty-compact"><h3>No invoices yet</h3><p class="muted">Issued invoices will appear here for you to view, download and pay.</p></div>
    @endforelse
    {{ $invoices->links() }}
</div></section>
@endsection
