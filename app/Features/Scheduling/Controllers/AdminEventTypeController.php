<?php

namespace App\Features\Scheduling\Controllers;

use App\Features\Scheduling\Actions\SaveEventTypeDefinition;
use App\Features\Scheduling\Models\EventTypeDefinition;
use App\Features\Scheduling\Requests\SaveEventTypeDefinitionRequest;
use App\Features\Scheduling\Support\SchedulingEventType;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AdminEventTypeController extends Controller
{
    public function index(): View
    {
        Gate::authorize('manageScheduling');

        return view('admin.event-management.event-types', [
            'eventTypes' => EventTypeDefinition::query()->orderBy('name')->get(),
            'systemCategories' => SchedulingEventType::cases(),
        ]);
    }

    public function store(SaveEventTypeDefinitionRequest $request, SaveEventTypeDefinition $saveEventType): RedirectResponse
    {
        $saveEventType->execute($request->validated());

        return back()->with('status', 'Event type added.');
    }

    public function update(SaveEventTypeDefinitionRequest $request, EventTypeDefinition $eventType, SaveEventTypeDefinition $saveEventType): RedirectResponse
    {
        $saveEventType->execute($request->validated(), $eventType);

        return back()->with('status', 'Event type updated.');
    }
}
