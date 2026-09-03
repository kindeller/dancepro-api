@extends('layouts.crew',['title'=>'My Timesheets'])
@section('content')
<div class="page-heading"><div><div class="crew-hub-brand"><img src="{{ asset('images/brand/dancepro-icon-blue.png') }}" alt=""><span>CREW HUB</span></div><h1>My Timesheets</h1><p class="muted">Select completed work to create an invoice or confirm that you sent your own.</p></div></div>

<section><div class="section-heading"><div><h2>Pending</h2><p class="muted">Completed shifts waiting to be invoiced. For hourly work, payable starts round down to the nearest 15 minutes without going before posted arrival, and finishes round up to the next 15 minutes.</p></div></div>
@forelse($pendingAssignments as $assignment)
@php
    $entry = $assignment->timeEntry;
    $preview = $previews[$assignment->id];
    $event = $assignment->shift->schedulingEvent;
    $timesComplete = $entry?->actual_clock_in_at && $entry?->actual_finish_at;
@endphp
<article class="card timesheet-pending-card">
    <div class="timesheet-pending-row">
        @if($timesComplete)<input class="timesheet-selector" type="checkbox" value="{{ $entry->id }}" aria-label="Select {{ $event->name }}" data-event-type="{{ $event->event_type->value }}" data-event-id="{{ $event->id }}">@else<span class="timesheet-checkbox-placeholder"></span>@endif
        <div><strong>{{ $event->name }}</strong><span class="muted" style="display:block">{{ $assignment->shift->shift_date->format('j M Y') }} · {{ $assignment->role->name }}@if($timesComplete) · Actual {{ $entry->actual_clock_in_at->format('g:i a') }}–{{ $entry->actual_finish_at->format('g:i a') }}@endif</span>@if($timesComplete && $preview['hours'] !== null)<span class="muted" style="display:block">Payable {{ $preview['payableStart']->format('g:i a') }}–{{ $preview['payableFinish']->format('g:i a') }} · {{ number_format((float)$preview['hours'],2) }} hours</span>@endif</div>
        <div class="timesheet-pending-status"><span class="status-pill attention">{{ $timesComplete ? 'Ready to invoice' : 'Confirm times' }}</span>@if($timesComplete)<strong>${{ number_format((float)$preview['total'],2) }}</strong>@endif</div>
    </div>
    <details class="venue-details timesheet-edit-times" @if(!$timesComplete) open @endif><summary>{{ $timesComplete ? 'Check or edit times' : 'Enter start and finish times' }}</summary><form method="POST" action="{{ route('crew.assignments.time.update',$assignment) }}" class="grid" style="margin-top:12px">@csrf @method('PUT')<div class="profile-grid"><label>Actual start<input type="time" name="actual_clock_in_at" value="{{ $entry?->actual_clock_in_at?->format('H:i') }}" required></label><label>Actual finish<input type="time" name="actual_finish_at" value="{{ $entry?->actual_finish_at?->format('H:i') }}" required></label><label class="wide">Optional note<input name="optional_note" value="{{ $entry?->optional_note }}" placeholder="For example: forgot to clock out"></label></div><button type="submit">Save times</button></form></details>
</article>
@empty<div class="card empty-state"><h3>Nothing pending</h3><p>Your completed invoiced work appears below.</p></div>@endforelse
</section>

@if($pendingAssignments->contains(fn ($assignment) => $assignment->timeEntry?->actual_clock_in_at && $assignment->timeEntry?->actual_finish_at))
<section id="invoice-actions" hidden><h2>What would you like to do?</h2><div class="profile-grid">
    <form class="card" method="POST" action="{{ route('crew.timesheets.invoices.preview') }}" data-selection-form>@csrf<div data-selected-inputs></div><h3>Produce invoice</h3><p class="muted">Preview an app-generated invoice, then accept it to send it directly to DancePro for payment.</p>@if($profile->next_invoice_number===null)<label>Your next unused invoice number<input type="number" min="1" name="starting_invoice_number" required></label>@else<p>Next invoice number: <strong>{{ $profile->next_invoice_number }}</strong></p>@endif<label>Invoice style<select name="invoice_style" required><option value="classic">Classic</option><option value="minimal">Minimal</option><option value="modern">Modern</option></select></label><button>Preview invoice</button></form>
    <form class="card" method="POST" action="{{ route('crew.timesheets.external-work-complete') }}" data-selection-form onsubmit="return confirm('Confirm that you have already emailed your own PDF invoice to DancePro.')">@csrf<div data-selected-inputs></div><h3>Invoice sent</h3><p class="muted">Use this only after emailing an invoice produced in your own accounting software.</p><button class="secondary">I sent my own invoice</button></form>
</div></section>
@endif

<section><div class="section-heading"><div><h2>Complete</h2><p class="muted">Invoices awaiting payment and previously completed work.</p></div></div>
@foreach($invoices as $invoice)<article class="card"><div class="section-heading"><div><strong>Invoice {{ $invoice->invoice_number }}</strong><div class="muted">{{ $invoice->schedulingEvent?->name ?: 'Concerts' }} · {{ $invoice->period_start->isSameDay($invoice->period_end) ? $invoice->period_start->format('j M Y') : $invoice->period_start->format('j M').'–'.$invoice->period_end->format('j M Y') }}</div></div><span class="status-pill {{ $invoice->status === 'paid' ? 'done' : 'attention' }}">{{ $invoice->status === 'pending_payment' ? 'Pending payment' : ucfirst(str_replace('_',' ',$invoice->status)) }}</span></div><div class="resource-actions invoice-card-actions"><strong>${{ number_format((float)$invoice->total,2) }}</strong><span></span><a class="button secondary" href="{{ route('crew.timesheets.invoices.show',$invoice) }}">View details</a><a class="button" target="_blank" href="{{ route('crew.timesheets.invoices.print',$invoice) }}">Print / Save PDF</a></div></article>@endforeach
@foreach($completedExternal as $entry)<article class="card"><div class="section-heading"><div><strong>{{ $entry->assignment->shift->schedulingEvent->name }}</strong><div class="muted">{{ $entry->assignment->shift->shift_date->format('j M Y') }} · Own invoice emailed</div></div><span class="status-pill done">Complete</span></div></article>@endforeach
@if($invoices->isEmpty()&&$completedExternal->isEmpty())<div class="card empty-state"><p>No completed invoices yet.</p></div>@endif
</section>
@endsection
@push('scripts')
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
const selectors=[...document.querySelectorAll('.timesheet-selector')];
const actions=document.getElementById('invoice-actions');
function syncSelection(changed){
    if(changed?.checked&&changed.dataset.eventType==='competition') selectors.forEach(box=>{box.checked=box.dataset.eventType==='competition'&&box.dataset.eventId===changed.dataset.eventId});
    const checked=selectors.filter(box=>box.checked); const competition=checked.find(box=>box.dataset.eventType==='competition'); const concert=checked.find(box=>box.dataset.eventType==='concert');
    selectors.forEach(box=>box.disabled=competition?!(box.dataset.eventType==='competition'&&box.dataset.eventId===competition.dataset.eventId):concert?box.dataset.eventType!=='concert':false);
    actions.hidden=checked.length===0;
    document.querySelectorAll('[data-selected-inputs]').forEach(container=>{container.replaceChildren(...checked.map(box=>{const input=document.createElement('input');input.type='hidden';input.name='entry_ids[]';input.value=box.value;return input}))});
}
selectors.forEach(box=>box.addEventListener('change',()=>syncSelection(box)));syncSelection();
</script>
@endpush
