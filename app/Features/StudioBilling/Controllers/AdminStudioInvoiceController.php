<?php

namespace App\Features\StudioBilling\Controllers;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\StudioBilling\Models\StudioBillingSetting;
use App\Features\StudioBilling\Models\StudioInvoice;
use App\Features\StudioBilling\Models\StudioInvoicePayment;
use App\Features\StudioBilling\Requests\ManageStudioInvoiceRequest;
use App\Features\StudioBilling\Services\BookingInvoiceSource;
use App\Features\StudioBilling\Services\InvoiceTrackerWorkflow;
use App\Features\StudioBilling\Services\PayStudioInvoice;
use App\Features\StudioBilling\Services\StudioInvoicePdf;
use App\Features\StudioBilling\Services\StudioInvoices;
use App\Features\Tracking\Services\EmailBranding;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AdminStudioInvoiceController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('manageScheduling');

        return view('admin.studio-invoices.index', ['invoices' => StudioInvoice::with('studio', 'payments')->latest('id')->paginate(30)]);
    }

    public function store(ManageStudioInvoiceRequest $request, ConcertBooking $concertBooking, StudioInvoices $invoices)
    {
        $invoice = $invoices->create($concertBooking, $request->user(), $request->boolean('is_test'), $request->validated('kind'));

        return redirect()->route('admin.studio-invoices.show', $invoice);
    }

    public function show(Request $request, StudioInvoice $studioInvoice, BookingInvoiceSource $source, InvoiceTrackerWorkflow $workflow, StudioInvoices $invoices)
    {
        Gate::authorize('manageScheduling');

        return view('admin.studio-invoices.show', ['invoice' => $studioInvoice->load('studio', 'booking', 'payments', 'events.user'), 'issueBlockers' => $studioInvoice->status === 'draft' ? $invoices->issueBlockers($studioInvoice) : [], 'stale' => $studioInvoice->source_hash !== $source->hash($studioInvoice->booking)] + $workflow->workspace($studioInvoice->booking, $request->session()->get('concert_tracker_context', [])));
    }

    public function email(Request $request, StudioInvoice $studioInvoice, InvoiceTrackerWorkflow $workflow, EmailBranding $branding)
    {
        Gate::authorize('manageScheduling');
        abort_unless($studioInvoice->status === 'issued' && $studioInvoice->active_booking_id, 422, 'Issue this invoice first.');
        $data = $workflow->workspace($studioInvoice->booking, $request->session()->get('concert_tracker_context', []));
        abort_unless($data['trackerBar'], 422, 'Link an active booking event first.');

        return response()->view('admin.tracking.task-email', $data + ['invoice' => $studioInvoice, 'emailBranding' => $branding->for($request->user()->id)])->header('Cache-Control', 'private, no-store');
    }

    public function update(ManageStudioInvoiceRequest $request, StudioInvoice $studioInvoice, StudioInvoices $invoices)
    {
        $invoices->save($studioInvoice, $request->user(), $request->validated());

        if ($request->boolean('send_invoice')) {
            $invoices->issue($studioInvoice, $request->user());

            return to_route('admin.studio-invoices.email', $studioInvoice)->with('status', 'Invoice added to the Studio Hub. Review and send the email below.');
        }

        return back()->with('status', 'Draft saved.');
    }

    public function refresh(ManageStudioInvoiceRequest $request, StudioInvoice $studioInvoice, StudioInvoices $invoices)
    {
        $invoices->refresh($studioInvoice, $request->user());

        return back()->with('status', 'Draft refreshed from the confirmed quote.');
    }

    public function issue(ManageStudioInvoiceRequest $request, StudioInvoice $studioInvoice, StudioInvoices $invoices)
    {
        $invoices->issue($studioInvoice, $request->user());

        return to_route('admin.studio-invoices.email', $studioInvoice)->with('status', 'Invoice added to the Studio Hub. Review and send the email below.');
    }

    public function void(ManageStudioInvoiceRequest $request, StudioInvoice $studioInvoice, StudioInvoices $invoices)
    {
        $invoices->void($studioInvoice, $request->user(), $request->validated('reason'));

        return back()->with('status', 'Invoice voided.');
    }

    public function recordPayment(ManageStudioInvoiceRequest $request, StudioInvoice $studioInvoice, StudioInvoices $invoices)
    {
        $invoices->recordPayment($studioInvoice, $request->user(), $request->validated());

        return back()->with('status', 'Payment recorded.');
    }

    public function reverse(ManageStudioInvoiceRequest $request, StudioInvoice $studioInvoice, StudioInvoicePayment $payment, StudioInvoices $invoices)
    {
        $invoices->reverse($studioInvoice, $payment, $request->user(), $request->validated('reason'));

        return back()->with('status', 'Manual payment record reversed. No money was refunded.');
    }

    public function reconcile(ManageStudioInvoiceRequest $request, StudioInvoice $studioInvoice, PayStudioInvoice $payments)
    {
        $payments->reconcile($studioInvoice, $request->user());

        return back()->with('status', 'Payment status checked. Unconfirmed payments remain pending.');
    }

    public function settings()
    {
        Gate::authorize('manageScheduling');

        return view('admin.studio-invoices.settings', ['settings' => StudioBillingSetting::first()]);
    }

    public function saveSettings(ManageStudioInvoiceRequest $request, StudioInvoices $invoices)
    {
        $invoices->saveSettings($request->validated());

        return back()->with('status', 'Invoice business details saved. Issued invoices retain their original details.');
    }

    public function pdf(StudioInvoice $studioInvoice, StudioInvoicePdf $pdf)
    {
        Gate::authorize('manageScheduling');

        return $pdf->download($studioInvoice);
    }
}
