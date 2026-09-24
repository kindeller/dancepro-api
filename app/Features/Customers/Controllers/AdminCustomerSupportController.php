<?php

namespace App\Features\Customers\Controllers;

use App\Features\Customers\Actions\ResolveCustomerSupportRequest;
use App\Features\Customers\Models\CustomerSupportRequest;
use App\Features\Customers\Requests\ResolveCustomerSupportRequest as ResolveRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AdminCustomerSupportController extends Controller
{
    public function index(Request $request): Response
    {
        $status = in_array($request->query('status'), ['pending', 'approved', 'declined', 'resolved'], true)
            ? $request->query('status')
            : 'pending';
        $requests = CustomerSupportRequest::query()->where('status', $status)
            ->with(['user', 'order', 'entitlement.orderItem', 'decidedBy'])
            ->orderByDesc('requested_at')->paginate(50)->withQueryString();

        return response()->view('admin.media.support', compact('requests', 'status'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function resolve(ResolveRequest $request, CustomerSupportRequest $supportRequest, ResolveCustomerSupportRequest $resolve): RedirectResponse
    {
        $resolved = $resolve->execute($supportRequest, $request->user(), $request->validated());

        return back()->with('status', 'Request marked '.$resolved->status.'.');
    }
}
