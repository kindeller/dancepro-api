<?php

namespace App\Features\StudioBilling\Controllers;

use App\Features\Bookings\Services\StudioBookingDrafts;
use App\Features\Bookings\Services\StudioBookingPlans;
use App\Features\Customers\Services\StudioCommunicationHistory;
use App\Features\StudioBilling\Models\StudioInvoice;
use App\Features\StudioBilling\Requests\PayStudioInvoiceRequest;
use App\Features\StudioBilling\Services\PayStudioInvoice;
use App\Features\StudioBilling\Services\StudioInvoiceAccess;
use App\Features\StudioBilling\Services\StudioInvoiceGateway;
use App\Features\StudioBilling\Services\StudioInvoicePdf;
use App\Features\Studios\Models\Studio;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CustomerStudioInvoiceController extends Controller
{
    public function __construct(private readonly StudioInvoiceAccess $access) {}

    public function index(Request $request, Studio $studio, StudioBookingPlans $plans, StudioCommunicationHistory $history)
    {
        $this->access->studio($request->user(), $studio);

        return response()->view('customers.studio-invoices.index', [
            'studio' => $studio,
            'hub' => $history->forUser($request->user(), $studio)['hub'],
            'estimates' => $plans->estimates($request->user(), $studio),
            'latestBooking' => $plans->latestForStudio($request->user(), $studio),
            'bookingDraft' => app(StudioBookingDrafts::class)->find($request->user(), $studio),
            'invoices' => StudioInvoice::with('payments')->where('studio_id', $studio->id)->where('status', '!=', 'draft')->latest('id')->paginate(20),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, Studio $studio, StudioInvoice $studioInvoice, StudioInvoiceGateway $gateway, StudioCommunicationHistory $history)
    {
        $this->access->invoice($request->user(), $studio, $studioInvoice);

        return response()->view('customers.studio-invoices.show', [
            'studio' => $studio,
            'hub' => $history->forUser($request->user(), $studio)['hub'],
            'invoice' => $studioInvoice->load('payments'),
            'gateway' => $gateway,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function token(Request $request, Studio $studio, StudioInvoice $studioInvoice, StudioInvoiceGateway $gateway)
    {
        $this->access->invoice($request->user(), $studio, $studioInvoice);
        abort_unless($studioInvoice->status === 'issued' && $studioInvoice->balanceCents() > 0, 422);

        return response()->json(['token' => $gateway->token($studioInvoice)])->header('Cache-Control', 'no-store');
    }

    public function pay(PayStudioInvoiceRequest $request, Studio $studio, StudioInvoice $studioInvoice, PayStudioInvoice $payments)
    {
        $payment = $payments->pay($studioInvoice, $request->user(), $request->validated());

        return redirect()->route('customer.studio-invoices.show', [$studio, $studioInvoice])->with('status', $payment->message ?? 'Payment is processing.');
    }

    public function reconcile(Request $request, Studio $studio, StudioInvoice $studioInvoice, PayStudioInvoice $payments)
    {
        $this->access->invoice($request->user(), $studio, $studioInvoice);
        $payments->reconcile($studioInvoice, $request->user());

        return back()->with('status', 'Payment status checked. Unconfirmed payments remain pending.');
    }

    public function pdf(Request $request, Studio $studio, StudioInvoice $studioInvoice, StudioInvoicePdf $pdf)
    {
        $this->access->invoice($request->user(), $studio, $studioInvoice);

        return $pdf->download($studioInvoice);
    }
}
