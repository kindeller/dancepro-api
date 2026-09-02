<?php

namespace App\Features\Exceptions\Controllers;

use App\Features\Exceptions\Services\AdminExceptionOverview;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AdminExceptionController extends Controller
{
    public function __invoke(Request $request, AdminExceptionOverview $overview): View
    {
        Gate::authorize('manageScheduling');
        $tabs = [
            'all' => 'All',
            'shifts-events' => 'Shifts & Events',
            'timekeeping' => 'Timekeeping',
            'payments' => 'Payments',
            'communication' => 'Communication',
        ];
        $activeTab = array_key_exists($request->string('tab')->toString(), $tabs) ? $request->string('tab')->toString() : 'all';
        $allExceptions = $overview->all();
        $exceptions = $activeTab === 'all' ? $allExceptions : $allExceptions->where('category', $activeTab)->values();
        $counts = collect($tabs)->mapWithKeys(fn ($label, $tab) => [$tab => $tab === 'all' ? $allExceptions->count() : $allExceptions->where('category', $tab)->count()]);

        return view('admin.exceptions.index', compact('tabs', 'activeTab', 'exceptions', 'counts'));
    }
}
