@extends('layouts.admin', ['title'=>$invoice->invoice_number ?: 'Draft invoice','heading'=>$invoice->invoice_number ?: 'Draft invoice','subheading'=>$invoice->crewProfile->preferred_name.' · '.ucfirst($invoice->status)])
@section('content')
@include('admin.timesheets._tabs')
@include('components.invoice-details',['invoice'=>$invoice])
<div class="actions" style="margin-top:18px">
    @if($invoice->status!=='draft' && $invoice->source==='dancepro')<a class="button" target="_blank" href="{{ route('admin.timesheets.invoices.print',$invoice) }}">Print / Save PDF</a>@endif
    @if($invoice->status !== 'draft')<a class="button secondary" href="{{ route('admin.timesheets.invoices.export',$invoice) }}">Export CSV</a>@endif
    @if(in_array($invoice->status,['pending_payment','approved','exported']))<form method="POST" action="{{ route('admin.timesheets.invoices.update',$invoice) }}">@csrf @method('PATCH')<button name="action" value="paid">Mark paid</button></form>@endif
</div>
@endsection
