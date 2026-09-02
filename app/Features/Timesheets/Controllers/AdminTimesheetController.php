<?php

namespace App\Features\Timesheets\Controllers;

use App\Features\Scheduling\Models\AssignmentTimeEntry;
use App\Features\Scheduling\Services\PaymentPreviewCalculator;
use App\Features\Timesheets\Models\CrewInvoice;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminTimesheetController extends Controller
{
    public function index(PaymentPreviewCalculator $calculator): View
    {
        Gate::authorize('manageScheduling');
        $entries = AssignmentTimeEntry::query()->whereNotNull('actual_finish_at')
            ->with(['assignment.crewProfile.user', 'assignment.role', 'assignment.allowances', 'assignment.shift.schedulingEvent', 'invoiceLine'])->latest('submitted_at')->get();
        $previews = $entries->mapWithKeys(fn ($entry) => [$entry->id => $calculator->execute($entry->assignment)]);

        return view('admin.timesheets.index', compact('entries', 'previews'));
    }

    public function invoices(): View
    {
        Gate::authorize('manageScheduling');

        return view('admin.timesheets.invoices', ['invoices' => CrewInvoice::query()->with(['crewProfile.user', 'lines', 'schedulingEvent'])->latest()->get()]);
    }

    public function showInvoice(CrewInvoice $invoice): View
    {
        Gate::authorize('manageScheduling');

        return view('admin.timesheets.invoice', ['invoice' => $invoice->load(['crewProfile.user', 'lines', 'schedulingEvent'])]);
    }

    public function updateInvoice(Request $request, CrewInvoice $invoice): RedirectResponse
    {
        Gate::authorize('manageScheduling');
        $action = $request->validate(['action' => ['required', 'in:export,paid']])['action'];
        if ($action === 'export') {
            abort_unless($invoice->status === 'approved', 422);
            $invoice->update(['status' => 'exported', 'exported_at' => now()]);
        } else {
            abort_unless(in_array($invoice->status, ['pending_payment', 'approved', 'exported'], true), 422);
            $invoice->update(['status' => 'paid', 'paid_at' => now()]);
        }

        return back()->with('status', 'Invoice updated.');
    }

    public function exportInvoice(CrewInvoice $invoice): StreamedResponse
    {
        Gate::authorize('manageScheduling');
        abort_unless(in_array($invoice->status, ['pending_payment', 'approved', 'exported', 'paid'], true), 422);
        $invoice->load(['crewProfile.user', 'lines']);
        if ($invoice->status === 'approved') {
            $invoice->update(['status' => 'exported', 'exported_at' => now()]);
        }

        return response()->streamDownload(function () use ($invoice): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Invoice', 'Crew member', 'Date', 'Event', 'Role', 'Hours', 'Base', 'Allowances', 'Total']);
            foreach ($invoice->lines as $line) {
                fputcsv($output, [$invoice->invoice_number, $invoice->crewProfile->legal_name, $line->snapshot['date'], $line->snapshot['event'], $line->snapshot['role'], $line->snapshot['hours'], $line->base_amount, $line->allowance_amount, $line->line_total]);
            }
            fclose($output);
        }, ($invoice->invoice_number ?: 'invoice-'.$invoice->id).'.csv', ['Content-Type' => 'text/csv']);
    }

    public function printInvoice(CrewInvoice $invoice): View
    {
        Gate::authorize('manageScheduling');
        abort_unless($invoice->status !== 'draft' && $invoice->source === 'dancepro', 422);

        return view('invoices.print', ['invoice' => $invoice->load(['crewProfile.user', 'lines', 'schedulingEvent'])]);
    }
}
