<?php

namespace App\Features\Timesheets\Controllers;

use App\Features\Timesheets\Models\CrewInvoice;
use App\Features\Timesheets\Services\CrewMobileFinancials;
use App\Http\Controllers\Controller;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrewMobileFinancialController extends Controller
{
    public function timesheets(Request $request, CrewMobileFinancials $financials): JsonResponse
    {
        return ApiResponse::success('Timesheets returned.', $financials->timesheets($request->user()->crewProfile));
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
}
