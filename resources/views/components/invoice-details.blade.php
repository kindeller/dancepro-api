<div class="card card-pad">
    <div class="invoice-overview">
        <div><span>Crew member</span><strong>{{ $invoice->crewProfile->legal_name }}</strong></div>
        <div><span>{{ $invoice->schedulingEvent ? 'Competition' : 'Work period' }}</span><strong>{{ $invoice->schedulingEvent?->name ?: ($invoice->period_start->isSameDay($invoice->period_end) ? $invoice->period_start->format('j M Y') : $invoice->period_start->format('j M').'–'.$invoice->period_end->format('j M Y')) }}</strong></div>
        <div><span>Invoice source</span><strong>{{ $invoice->source === 'external' ? 'Supplied by crew member' : 'Generated through DancePro' }}</strong></div>
    </div>
    <div class="invoice-table-wrap"><table class="invoice-table"><thead><tr><th>Date</th><th>Event / role</th><th>Hours / rate</th><th>Base</th><th>Allowances</th><th>Total</th></tr></thead><tbody>
    @foreach($invoice->lines as $line)<tr><td>{{ \Carbon\Carbon::parse($line->snapshot['date'])->format('j M Y') }}</td><td><strong>{{ $line->snapshot['event'] }}</strong><div class="muted">{{ $line->snapshot['role'] }}</div></td><td>{{ $line->snapshot['hours'] ?? 'Fixed' }}<div class="muted">{{ $line->snapshot['rate_name'] }} @if($line->snapshot['rate']) · ${{ number_format((float)$line->snapshot['rate'],2) }}@endif</div></td><td>${{ number_format((float)$line->base_amount,2) }}</td><td>${{ number_format((float)$line->allowance_amount,2) }}</td><td><strong>${{ number_format((float)$line->line_total,2) }}</strong></td></tr>@endforeach
    </tbody><tfoot><tr><th colspan="5">Invoice total</th><th>${{ number_format((float)$invoice->total,2) }}</th></tr></tfoot></table></div>
</div>
