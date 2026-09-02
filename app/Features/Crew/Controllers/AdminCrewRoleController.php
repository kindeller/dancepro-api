<?php

namespace App\Features\Crew\Controllers;

use App\Features\Crew\Actions\CreateCrewRole;
use App\Features\Crew\Actions\UpdateCrewRole;
use App\Features\Crew\Actions\UpdateCrewRoleMatrix;
use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Models\CrewRole;
use App\Features\Crew\Requests\StoreCrewRoleRequest;
use App\Features\Crew\Requests\UpdateCrewRoleMatrixRequest;
use App\Features\Crew\Requests\UpdateCrewRoleRequest;
use App\Features\Scheduling\Models\EventTypeDefinition;
use App\Features\Scheduling\Support\SchedulingEventType;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AdminCrewRoleController extends Controller
{
    public function index(): View
    {
        Gate::authorize('manageCrew');

        $roles = CrewRole::query()->orderBy('name')->get();

        return view('admin.crew-management.roles', [
            'roles' => $roles,
            'eventTypes' => SchedulingEventType::cases(),
            'eventTypeDefinitions' => EventTypeDefinition::query()->where('is_active', true)->orderBy('name')->get(),
            'matrixRoles' => $roles->where('is_active', true),
            'crewProfiles' => CrewProfile::query()
                ->with(['user', 'roleQualifications'])
                ->whereHas('user', fn ($query) => $query->where('is_active', true))
                ->orderBy('preferred_name')
                ->get(),
        ]);
    }

    public function store(StoreCrewRoleRequest $request, CreateCrewRole $createCrewRole): RedirectResponse
    {
        $createCrewRole->execute($request->safe()->merge(['is_active' => true])->all());

        return redirect()->route('admin.crew-roles.index')->with('status', 'Crew role added.');
    }

    public function update(UpdateCrewRoleRequest $request, CrewRole $crewRole, UpdateCrewRole $updateCrewRole): RedirectResponse
    {
        $updateCrewRole->execute($crewRole, $request->validated());

        return redirect()->route('admin.crew-roles.index')->with('status', 'Crew role updated.');
    }

    public function updateMatrix(UpdateCrewRoleMatrixRequest $request, UpdateCrewRoleMatrix $updateCrewRoleMatrix): RedirectResponse
    {
        $updateCrewRoleMatrix->execute($request->validated());

        return redirect()->route('admin.crew-roles.index')->with('status', 'Crew role assignments updated.');
    }
}
