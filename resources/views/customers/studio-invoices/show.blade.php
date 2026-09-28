@extends('layouts.public',['title'=>'Invoice '.$invoice->number(),'footerLabel'=>'Studio Hub'])
@section('headerParentUrl',route('customer.studio-invoices.index',$studio))
@include('customers.studios._styles')
@section('content')
@include('studio-invoices._styles')
@include('customers.studios._header')
@include('customers.studios._tabs', ['tab' => 'invoices'])
<section class="section"><div class="container" style="max-width:1000px"><div class="studio-billing-actions"><a class="button secondary" href="{{ route('customer.studio-invoices.pdf',[$studio,$invoice]) }}">Download PDF</a></div>
@if(session('status'))<p class="studio-billing-notice" role="status">{{ session('status') }}</p>@endif
@if($errors->any())<div class="studio-billing-notice" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
@include('studio-invoices._details')
@if($invoice->status==='issued' && $invoice->balanceCents()>0)
@if($invoice->hasPendingPayment())<form method="POST" action="{{ route('customer.studio-invoices.reconcile',[$studio,$invoice]) }}">@csrf<p class="studio-billing-notice">Your payment is being verified. Please do not pay again.</p><button class="button">Check payment status</button></form>
@elseif($gateway->available($invoice))
<form class="studio-billing-form" id="invoice-payment" method="POST" action="{{ route('customer.studio-invoices.pay',[$studio,$invoice]) }}" data-token-url="{{ route('customer.studio-invoices.token',[$studio,$invoice]) }}" data-mode="{{ $gateway->mode() }}">@csrf<input type="hidden" name="idempotency_key" value="{{ Str::uuid() }}"><input type="hidden" name="balance_cents" value="{{ $invoice->balanceCents() }}"><input type="hidden" name="nonce" id="payment-nonce">
<h2>{{ $invoice->is_test ? 'Test payment' : 'Pay securely by card' }}</h2>
@if($invoice->is_test)<p class="studio-billing-notice">Rehearsal invoice. No real money will be charged.</p>@endif
@if($gateway->mode()==='braintree')<label>Card number<div class="studio-billing-card-field" id="card-number"></div></label><label>Expiry<div class="studio-billing-card-field" id="card-expiry"></div></label><label>Security code<div class="studio-billing-card-field" id="card-cvv"></div></label>@endif
<p id="payment-error" role="alert"></p><button class="button" id="pay-invoice" @disabled($gateway->mode()==='braintree')>{{ $invoice->is_test ? 'Simulate payment' : 'Pay' }} ${{ number_format($invoice->balanceCents()/100,2) }} AUD</button></form>
@if($gateway->mode()==='braintree')
@foreach(['client','hosted-fields','three-d-secure'] as $library)<script nonce="{{ request()->attributes->get('csp_nonce') }}" src="https://js.braintreegateway.com/web/3.146.0/js/{{ $library }}.min.js"></script>@endforeach
<script nonce="{{ request()->attributes->get('csp_nonce') }}" src="{{ asset('js/studio-invoice-payment.js') }}"></script>
@endif
@else<p class="studio-billing-notice">Online card payments are not available for this invoice yet. Use the direct transfer details above, or contact {{ $invoice->issuer['email'] ?? 'DancePro' }}.</p>@endif
@endif
</div></section>
@endsection
