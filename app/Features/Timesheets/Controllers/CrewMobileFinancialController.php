<?php

namespace App\Features\Timesheets\Controllers;

use App\Features\Timesheets\Actions\CreateSelectedCrewInvoice;
use App\Features\Timesheets\Actions\MarkExternalWorkComplete;
use App\Features\Timesheets\Models\CrewInvoice;
use App\Features\Timesheets\Requests\CrewMobileExternalInvoiceRequest;
use App\Features\Timesheets\Requests\CrewMobileInvoiceSelectionRequest;
use App\Features\Timesheets\Requests\ListCrewMobileTimesheetsRequest;
use App\Features\Timesheets\Services\CrewInvoicePdf;
use App\Features\Timesheets\Services\CrewInvoiceSelection;
use App\Features\Timesheets\Services\CrewMobileFinancials;
use App\Http\Controllers\Controller;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CrewMobileFinancialController extends Controller
{
    public function timesheets(ListCrewMobileTimesheetsRequest $request, CrewMobileFinancials $financials): JsonResponse
    {
        $page = $financials->timesheets(
            $request->user()->crewProfile,
            $request->integer('limit', 25),
            $request->validated(),
        );

        return ApiResponse::success('Timesheets returned.', collect($page->items())->map($financials->timesheetResource(...)), meta: [
            'next_cursor' => $page->nextCursor()?->encode(),
            'has_more' => $page->hasMorePages(),
        ]);
    }

    public function invoices(Request $request, CrewMobileFinancials $financials): JsonResponse
    {
        $page = $financials->invoices($request->user()->crewProfile, min(max($request->integer('limit', 25), 1), 100));

        return ApiResponse::success('Invoices returned.', collect($page->items())->map($financials->invoiceSummary(...)), meta: [
            'next_cursor' => $page->nextCursor()?->encode(),
            'has_more' => $page->hasMorePages(),
        ]);
    }

    public function invoice(Request $request, CrewInvoice $invoice, CrewMobileFinancials $financials): JsonResponse
    {
        $invoice = $financials->invoice($request->user()->crewProfile, $invoice);

        return ApiResponse::success('Invoice returned.', $financials->invoiceDetail($invoice));
    }

    public function previewInvoice(CrewMobileInvoiceSelectionRequest $request, CrewInvoiceSelection $selection, CrewMobileFinancials $financials): JsonResponse
    {
        $data = $request->validated();
        $resolved = $selection->resolveAssignmentUuids($request->user()->crewProfile, $data['assignment_ids']);

        return ApiResponse::success('Invoice preview returned.', $financials->invoicePreview(
            $resolved,
            $data['invoice_style'],
            $data['starting_invoice_number'] ?? null,
        ));
    }

    public function createInvoice(CrewMobileInvoiceSelectionRequest $request, CreateSelectedCrewInvoice $create, CrewMobileFinancials $financials): JsonResponse
    {
        $data = $request->validated();
        $invoice = $create->executeForAssignments($request->user()->crewProfile, $data['assignment_ids'], $data['invoice_style'], $data['starting_invoice_number'] ?? null);

        return ApiResponse::success('Invoice submitted.', $financials->invoiceDetail($invoice), 201);
    }

    public function markExternalInvoice(CrewMobileExternalInvoiceRequest $request, MarkExternalWorkComplete $markComplete): JsonResponse
    {
        $count = $markComplete->executeForAssignments($request->user()->crewProfile, $request->validated('assignment_ids'));

        return ApiResponse::success('External invoice recorded.', ['updated_count' => $count]);
    }

    public function downloadInvoice(Request $request, CrewInvoice $invoice, CrewMobileFinancials $financials, CrewInvoicePdf $pdf): JsonResponse
    {
        $invoice = $financials->invoice($request->user()->crewProfile, $invoice);
        $document = $pdf->document($invoice);
        $expiresAt = now()->addMinutes(5);

        return ApiResponse::success('Temporary invoice download issued.', [
            'url' => URL::temporarySignedRoute('api.v1.invoices.content', $expiresAt, ['invoice' => $invoice]),
            'expires_at' => $expiresAt->toIso8601String(),
            'checksum' => $document['checksum'],
            'bytes' => $document['bytes'],
        ]);
    }

    public function invoiceContent(Request $request, CrewInvoice $invoice, CrewMobileFinancials $financials, CrewInvoicePdf $pdf): StreamedResponse
    {
        $invoice = $financials->invoice($request->user()->crewProfile, $invoice);
        $document = $pdf->document($invoice);

        return $pdf->disk()->response($document['path'], 'invoice-'.$invoice->invoice_number.'.pdf', [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store',
        ], 'attachment');
    }
}
