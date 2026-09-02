<?php

namespace App\Features\Operations\Controllers;

use App\Features\Exceptions\Services\AdminExceptionOverview;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Scheduling\Models\SchedulingShiftAssignment;
use App\Features\Scheduling\Models\ShiftCoverRequest;
use App\Features\Timesheets\Models\CrewInvoice;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AdminHubDashboardController extends Controller
{
    public function __invoke(AdminExceptionOverview $exceptionOverview): View
    {
        Gate::authorize('manageScheduling');

        $upcomingEventQuery = SchedulingEvent::query()
            ->whereBetween('event_date', [today(), today()->addDays(14)]);
        $upcomingEventCount = (clone $upcomingEventQuery)->count();
        $upcomingEvents = $upcomingEventQuery
            ->with(['venue', 'shifts.assignments.timeEntry'])
            ->orderBy('event_date')
            ->limit(8)
            ->get();

        $exceptions = $exceptionOverview->all();

        return view('admin.hub-dashboard', [
            'totals' => [
                'events' => $upcomingEventCount,
                'assignedCrew' => SchedulingShiftAssignment::query()
                    ->where('status', 'published')
                    ->whereHas('shift', fn ($query) => $query->whereBetween('shift_date', [today(), today()->addDays(14)]))
                    ->count(),
                'coverRequests' => ShiftCoverRequest::query()->where('status', 'open')->count(),
                'pendingInvoices' => CrewInvoice::query()->where('status', 'pending_payment')->count(),
            ],
            'upcomingEvents' => $upcomingEvents,
            'exceptions' => $exceptions->take(8),
            'exceptionCount' => $exceptions->count(),
        ]);
    }
}
