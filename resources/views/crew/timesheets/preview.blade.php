@extends('layouts.crew',['title'=>'Preview Invoice'])
@section('content')
@php
    $invoiceNumber = $profile->next_invoice_number ?? $startingNumber;
    $dates = $entries->pluck('assignment.shift.shift_date')->sort()->values();
    $period = $dates->first()->isSameDay($dates->last()) ? $dates->first()->format('j M Y') : $dates->first()->format('j M').'–'.$dates->last()->format('j M Y');
@endphp
<div class="page-heading"><div><span class="eyebrow">My Timesheets</span><h1>Invoice {{ $invoiceNumber }} preview</h1><span class="status-pill attention">Preview only</span></div><a class="button secondary" href="{{ route('crew.timesheets.index') }}">Back to timesheets</a></div>
<p class="muted invoice-preview-note">Check every detail before accepting. Accepting sends this invoice directly to DancePro as pending payment.</p>
<div class="card card-pad">
    <div class="invoice-overview">
        <div><span>Crew member</span><strong>{{ $profile->legal_name }}</strong></div>
        <div><span>{{ $event ? 'Competition' : 'Work period' }}</span><strong>{{ $event?->name ?: $period }}</strong></div>
        <div><span>Invoice style</span><strong>{{ ucfirst($style) }}</strong></div>
    </div>
    <div class="invoice-table-wrap"><table class="invoice-table"><thead><tr><th>Date</th><th>Event / role</th><th>Hours / rate</th><th>Base</th><th>Allowances</th><th>Total</th></tr></thead><tbody>
    @foreach($entries as $entry)
    @php($preview = $previews[$entry->id])
    <tr><td>{{ $entry->assignment->shift->shift_date->format('j M Y') }}</td><td><strong>{{ $entry->assignment->shift->schedulingEvent->name }}</strong><div class="muted">{{ $entry->assignment->role->name }}</div></td><td>{{ $preview['hours'] ?? 'Fixed' }}<div class="muted">{{ $preview['rate']?->name }} @if($preview['rate']) · ${{ number_format((float)$preview['rate']->amount,2) }}@endif</div></td><td>${{ number_format((float)$preview['base'],2) }}</td><td>${{ number_format((float)$preview['allowanceTotal'],2) }}</td><td><strong>${{ number_format((float)$preview['total'],2) }}</strong></td></tr>
    @endforeach
    </tbody><tfoot><tr><th colspan="5">Invoice total</th><th>${{ number_format((float)$previews->sum('total'),2) }}</th></tr></tfoot></table></div>
</div>
<form class="invoice-preview-actions" method="POST" action="{{ route('crew.timesheets.invoices.accept') }}">@csrf @foreach($entries as $entry)<input type="hidden" name="entry_ids[]" value="{{ $entry->id }}">@endforeach<input type="hidden" name="invoice_style" value="{{ $style }}">@if($startingNumber)<input type="hidden" name="starting_invoice_number" value="{{ $startingNumber }}">@endif<a class="button secondary" href="{{ route('crew.timesheets.index') }}">Change selection</a><button>Accept and send for payment</button></form>
@endsection
