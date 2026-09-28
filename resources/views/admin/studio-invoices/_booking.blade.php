@if($booking->status->value === 'approved' && $booking->studio_id)
<div class="card card-pad booking-section" id="studio-invoice"><h2>Studio invoice</h2>
@php($bookingInvoice = $booking->activeStudioInvoice)
@if($bookingInvoice)<p>Invoice {{ $bookingInvoice->number() }} · {{ $bookingInvoice->paymentLabel() }} · ${{ number_format($bookingInvoice->balanceCents()/100,2) }} outstanding</p><a class="button secondary" href="{{ route('admin.studio-invoices.show',$bookingInvoice) }}">View invoice</a>
@else<p>Create a draft from confirmed video pricing, or enter custom charges. Photography minimum-spend shortfalls require a separate review and are never charged automatically.</p><form method="POST" action="{{ route('admin.studio-invoices.store',$booking) }}">@csrf<div style="display:flex;gap:16px;flex-wrap:wrap;align-items:center"><label>Invoice type<select name="kind"><option value="video">Confirmed video quote</option><option value="custom">Custom invoice</option></select></label><label style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="is_test" value="1" @checked(app()->environment(['local','testing']))> Test invoice (no real payment)</label><button>Create draft invoice</button></div></form>@endif
</div>
@endif
