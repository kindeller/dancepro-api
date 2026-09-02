<?php

namespace App\Features\Timesheets\Controllers;

use App\Features\Scheduling\Models\SchedulingShiftAssignment;
use App\Features\Scheduling\Services\PaymentPreviewCalculator;
use App\Features\Timesheets\Actions\CreateSelectedCrewInvoice;
use App\Features\Timesheets\Actions\MarkExternalWorkComplete;
use App\Features\Timesheets\Models\CrewInvoice;
use App\Features\Timesheets\Requests\MarkExternalWorkCompleteRequest;
use App\Features\Timesheets\Requests\PreviewCrewInvoiceRequest;
use App\Features\Timesheets\Services\CrewInvoiceSelection;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CrewTimesheetController extends Controller
{
    public function index(Request $request, PaymentPreviewCalculator $calculator): View
    {
        $profile = $request->user()?->crewProfile;
        abort_unless($profile, 403);
        $assignments = SchedulingShiftAssignment::query()
            ->where('crew_profile_id', $profile->id)
            ->where('status', 'published')
            ->whereHas('shift', fn ($query) => $query->whereDate('shift_date', '<=', today()))
            ->with(['role', 'allowances', 'shift.schedulingEvent', 'timeEntry.invoiceLine.invoice'])
            ->get()
            ->sortByDesc(fn ($assignment) => $assignment->shift->shift_date);
        $entries = $assignments->pluck('timeEntry')->filter();
        $pendingAssignments = $assignments->filter(fn ($assignment) => ! $assignment->timeEntry?->invoiceLine && $assignment->timeEntry?->approval_status !== 'externally_invoiced');
        $completedExternal = $entries->where('approval_status', 'externally_invoiced');

        return view('crew.timesheets.index', ['profile' => $profile, 'pendingAssignments' => $pendingAssignments, 'completedExternal' => $completedExternal, 'previews' => $assignments->mapWithKeys(fn ($assignment) => [$assignment->id => $calculator->execute($assignment)]), 'invoices' => CrewInvoice::query()->where('crew_profile_id', $profile->id)->with(['lines', 'schedulingEvent'])->latest()->get()]);
    }

    public function markExternalWorkComplete(MarkExternalWorkCompleteRequest $request, MarkExternalWorkComplete $markComplete): RedirectResponse
    {
        $profile = $request->user()->crewProfile;
        $count = $markComplete->execute($profile, $request->validated('entry_ids'));

        return back()->with('status', $count.' time '.str('entry')->plural($count).' marked complete. Your external invoice is not stored in DancePro.');
    }

    public function previewInvoice(PreviewCrewInvoiceRequest $request, CrewInvoiceSelection $selection): View
    {
        $data = $request->validated();
        $resolved = $selection->resolve($request->user()->crewProfile, $data['entry_ids']);

        return view('crew.timesheets.preview', [...$resolved, 'profile' => $request->user()->crewProfile, 'style' => $data['invoice_style'], 'startingNumber' => $data['starting_invoice_number'] ?? null]);
    }

    public function acceptInvoice(PreviewCrewInvoiceRequest $request, CreateSelectedCrewInvoice $create): RedirectResponse
    {
        $data = $request->validated();
        $invoice = $create->execute($request->user()->crewProfile, $data['entry_ids'], $data['invoice_style'], $data['starting_invoice_number'] ?? null);

        return redirect()->route('crew.timesheets.invoices.show', $invoice)->with('status', 'Invoice accepted and sent to DancePro for payment.');
    }

    public function invoice(Request $request, CrewInvoice $invoice): View
    {
        abort_unless($invoice->crew_profile_id === $request->user()?->crewProfile?->id, 403);

        return view('crew.timesheets.invoice', ['invoice' => $invoice->load(['crewProfile.user', 'lines', 'schedulingEvent'])]);
    }

    public function printInvoice(Request $request, CrewInvoice $invoice): View
    {
        abort_unless($invoice->crew_profile_id === $request->user()?->crewProfile?->id, 403);
        abort_unless($invoice->status !== 'draft' && $invoice->source === 'dancepro', 422);

        return view('invoices.print', ['invoice' => $invoice->load(['crewProfile.user', 'lines', 'schedulingEvent'])]);
    }
}
