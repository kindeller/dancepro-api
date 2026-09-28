<div class="booking-invoice-workspace">
    @if($invoice)
        @include('admin.studio-invoices._editor')
    @else
        @include('admin.studio-invoices._prepare')
    @endif
    @if($invoiceHistory->contains(fn ($record) => $record->id !== $invoice?->id))
        <details class="card card-pad" style="margin-top:20px">
            <summary>Other invoices for this booking</summary>
            @foreach($invoiceHistory as $record)
                @if($record->id !== $invoice?->id)
                    <p><a href="{{ route('admin.concert-bookings.show', [$booking, 'tab' => 'invoice', 'invoice' => $record->uuid]) }}">Invoice {{ $record->number() }}</a> · {{ $record->paymentLabel() }}</p>
                @endif
            @endforeach
            @if($invoice && !$invoice->active_booking_id)
                <a class="button secondary" href="{{ route('admin.concert-bookings.show', [$booking, 'tab' => 'invoice']) }}">Current invoice workspace</a>
            @endif
        </details>
    @elseif($invoice && !$invoice->active_booking_id)
        <a class="button secondary" href="{{ route('admin.concert-bookings.show', [$booking, 'tab' => 'invoice']) }}">Create replacement invoice</a>
    @endif
</div>
