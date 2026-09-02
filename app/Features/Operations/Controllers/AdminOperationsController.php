<?php

namespace App\Features\Operations\Controllers;

use App\Features\Operations\Actions\SaveChecklistTemplate;
use App\Features\Operations\Actions\SaveEventOperations;
use App\Features\Operations\Actions\SaveOperationalResource;
use App\Features\Operations\Actions\SaveVenueMap;
use App\Features\Operations\Models\ChecklistTemplate;
use App\Features\Operations\Models\OperationalResource;
use App\Features\Operations\Requests\SaveChecklistTemplateRequest;
use App\Features\Operations\Requests\SaveEventOperationsRequest;
use App\Features\Operations\Requests\SaveOperationalResourceRequest;
use App\Features\Operations\Requests\SaveVenueMapRequest;
use App\Features\Scheduling\Models\EventTypeDefinition;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Venues\Models\Venue;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AdminOperationsController extends Controller
{
    public function index(): RedirectResponse
    {
        Gate::authorize('manageScheduling');

        return redirect()->route('admin.crew-management.resources');
    }

    public function resources(): View
    {
        Gate::authorize('manageScheduling');

        return view('admin.operations.resources', [
            'resources' => OperationalResource::query()->orderBy('sort_order')->orderBy('title')->get(),
            'eventTypes' => EventTypeDefinition::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function checklists(): View
    {
        Gate::authorize('manageScheduling');

        return view('admin.operations.checklists', [
            'templates' => ChecklistTemplate::query()->with('items')->orderBy('name')->get(),
            'eventTypes' => EventTypeDefinition::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function storeResource(SaveOperationalResourceRequest $request, SaveOperationalResource $save): RedirectResponse
    {
        $save->execute($request->validated());

        return back()->with('status', 'Resource added.');
    }

    public function updateResource(SaveOperationalResourceRequest $request, OperationalResource $resource, SaveOperationalResource $save): RedirectResponse
    {
        $save->execute($request->validated(), $resource);

        return back()->with('status', 'Resource updated.');
    }

    public function storeChecklist(SaveChecklistTemplateRequest $request, SaveChecklistTemplate $save): RedirectResponse
    {
        $save->execute($request->validated());

        return back()->with('status', 'Checklist template added.');
    }

    public function updateChecklist(SaveChecklistTemplateRequest $request, ChecklistTemplate $template, SaveChecklistTemplate $save): RedirectResponse
    {
        $save->execute($request->validated(), $template);

        return back()->with('status', 'Checklist template updated.');
    }

    public function updateEvent(SaveEventOperationsRequest $request, SchedulingEvent $schedulingEvent, SaveEventOperations $save): RedirectResponse
    {
        $save->execute($schedulingEvent, $request->validated());

        return back()->with('status', 'Event information updated.');
    }

    public function updateVenueMap(SaveVenueMapRequest $request, Venue $venue, SaveVenueMap $save): RedirectResponse
    {
        $save->execute($venue, $request->file('map'));

        return back()->with('status', 'Venue map updated.');
    }
}
