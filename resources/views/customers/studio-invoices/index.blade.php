@extends('layouts.public',['title'=>'Studio invoices','footerLabel'=>'Studio Hub'])
@section('headerParentUrl',route('customer.studio-communications.index',$studio))
@section('content')
@include('studio-invoices._styles')
<section class="hero"><div class="container"><div class="eyebrow">Studio Hub</div><h1>Invoices</h1><p class="lead">{{ $studio->name }}</p></div></section><section class="section"><div class="container" style="max-width:1000px"><div class="studio-billing-actions"><a class="button secondary" href="{{ route('customer.studio-communications.index',$studio) }}">Events</a><a class="button secondary" href="{{ route('customer.studio-bookings.index',$studio) }}">Event timeline</a></div>
@forelse($invoices as $invoice)<article class="studio-billing-sheet"><div class="studio-billing-heading"><div><h2>Invoice {{ $invoice->number() }}</h2><p>{{ $invoice->is_test ? 'TEST · ' : '' }}{{ $invoice->paymentLabel() }} · Due {{ $invoice->due_date->format('j M Y') }}</p></div><p>${{ number_format($invoice->balanceCents()/100,2) }} outstanding</p></div><div class="studio-billing-actions"><a class="button" href="{{ route('customer.studio-invoices.show',[$studio,$invoice]) }}">View invoice</a><a class="button secondary" href="{{ route('customer.studio-invoices.pdf',[$studio,$invoice]) }}">Download PDF</a></div></article>@empty<p class="studio-billing-notice">There are no issued invoices for your studio yet.</p>@endforelse
{{ $invoices->links() }}</div></section>
@endsection
