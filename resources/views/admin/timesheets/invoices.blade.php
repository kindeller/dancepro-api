@extends('layouts.admin', ['title'=>'Crew Payments · Invoices','heading'=>'Crew Payments','subheading'=>'App-generated contractor invoices appear here immediately after crew accept their preview.'])
@section('content')
@include('admin.timesheets._tabs')
<div class="card"><table><thead><tr><th>Invoice</th><th>Crew</th><th>Work</th><th>Period</th><th>Total</th><th>Status</th><th></th></tr></thead><tbody>
@forelse($invoices as $invoice)<tr><td>{{ $invoice->invoice_number ?: 'Draft #'.$invoice->id }}</td><td>{{ $invoice->crewProfile->preferred_name }}</td><td>{{ $invoice->schedulingEvent?->name ?: 'Concerts' }}</td><td>{{ $invoice->period_start->format('j M') }}–{{ $invoice->period_end->format('j M Y') }}</td><td><strong>${{ number_format((float)$invoice->total,2) }}</strong></td><td><span class="badge {{ $invoice->status === 'paid' ? 'success' : 'attention' }}">{{ $invoice->status === 'pending_payment' ? 'Pending payment' : ucfirst($invoice->status) }}</span></td><td><a class="button secondary" href="{{ route('admin.timesheets.invoices.show',$invoice) }}">Open</a></td></tr>@empty<tr><td colspan="7" class="muted">No app-generated invoices yet.</td></tr>@endforelse
</tbody></table></div>
@endsection
