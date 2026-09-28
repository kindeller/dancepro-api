@extends('layouts.admin',['title'=>'Studio invoices','heading'=>'Studio invoices','subheading'=>'Booking invoices, balances and payments'])
@section('content')
@include('studio-invoices._styles')
<div class="studio-billing-actions"><a class="button secondary" href="{{ route('admin.concert-bookings.index') }}">Event bookings</a><a class="button secondary" href="{{ route('admin.studio-invoices.settings') }}">Invoice settings</a></div><p>Create an invoice from an approved event booking. Drafts stay private until issued.</p>
@forelse($invoices as $invoice)<article class="studio-billing-sheet"><div class="studio-billing-heading"><div><a href="{{ route('admin.studio-invoices.show',$invoice) }}"><strong>Invoice {{ $invoice->number() }} · {{ $invoice->studio->name }}</strong></a><p>{{ $invoice->is_test ? 'TEST · ' : '' }}{{ $invoice->paymentLabel() }} · Due {{ $invoice->due_date->format('j M Y') }}</p></div><p>${{ number_format($invoice->balanceCents()/100,2) }} outstanding</p></div></article>@empty<div class="studio-billing-notice">No studio invoices yet.</div>@endforelse
{{ $invoices->links() }}
@endsection
