<?php

namespace App\Features\Customers\Controllers;

use App\Features\Customers\Actions\ResolveCustomerSupportRequest;
use App\Features\Customers\Models\CustomerSupportRequest;
use App\Features\Customers\Requests\ResolveCustomerSupportRequest as ResolveRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminCustomerSupportController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('admin.messages.index', ['channel' => 'customer', 'status' => in_array($request->query('status'), ['pending', 'approved', 'declined', 'resolved']) ? $request->query('status') : 'pending']);
    }

    public function resolve(ResolveRequest $request, CustomerSupportRequest $supportRequest, ResolveCustomerSupportRequest $resolve): RedirectResponse
    {
        $resolved = $resolve->execute($supportRequest, $request->user(), $request->validated());

        return back()->with('status', 'Request marked '.$resolved->status.'.');
    }
}
