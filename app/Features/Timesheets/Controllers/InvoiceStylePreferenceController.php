<?php

namespace App\Features\Timesheets\Controllers;

use App\Features\Timesheets\Requests\UpdateInvoiceStyleRequest;
use App\Http\Controllers\Controller;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceStylePreferenceController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $profile = $request->user()->crewProfile;
        abort_unless($profile, 403);

        return ApiResponse::success('Invoice style returned.', ['invoice_style' => $profile->preferred_invoice_style]);
    }

    public function update(UpdateInvoiceStyleRequest $request): JsonResponse
    {
        $profile = $request->user()->crewProfile;
        $profile->preferred_invoice_style = $request->validated('invoice_style');
        $profile->save();

        return ApiResponse::success('Invoice style saved.', ['invoice_style' => $profile->preferred_invoice_style]);
    }
}
