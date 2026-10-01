<?php

namespace App\Features\Admin\Controllers;

use App\Features\Admin\Requests\ReviewCustomerStudioRequest;
use App\Features\Customers\Models\CustomerStudioRequest;
use App\Features\Studios\Services\StudioMemberships;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;

class AdminStudioRequestController extends Controller
{
    public function index(StudioMemberships $memberships)
    {
        Gate::authorize('manageStudios');

        return view('admin.studio-requests.index', ['submissions' => $memberships->queue()]);
    }

    public function show(CustomerStudioRequest $submission, StudioMemberships $memberships)
    {
        Gate::authorize('manageStudios');

        return view('admin.studio-requests.show', $memberships->reviewPage($submission));
    }

    public function store(ReviewCustomerStudioRequest $request, CustomerStudioRequest $submission, StudioMemberships $memberships)
    {
        $memberships->review($submission, $request->user(), $request->validated());

        return to_route('admin.studio-requests.show', $submission)->with('status', 'Review saved. Approved studios are now attached to the requesting account.');
    }
}
