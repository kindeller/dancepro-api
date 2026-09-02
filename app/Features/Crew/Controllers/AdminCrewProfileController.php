<?php

namespace App\Features\Crew\Controllers;

use App\Features\Crew\Actions\InviteCrewMember;
use App\Features\Crew\Actions\SaveCrewProfile;
use App\Features\Crew\Models\CrewContract;
use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Models\CrewRole;
use App\Features\Crew\Requests\InviteCrewMemberRequest;
use App\Features\Crew\Requests\SaveCrewProfileRequest;
use App\Features\Crew\Support\CrewRoleQualificationStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AdminCrewProfileController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('manageCrew');

        $crewProfiles = CrewProfile::query()
            ->with(['user', 'roles'])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(function ($query) use ($search): void {
                    $query->where('preferred_name', 'like', "%{$search}%")
                        ->orWhere('legal_name', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($query) => $query->where('email', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('active'), fn ($query) => $query->whereHas('user', fn ($query) => $query->where('is_active', $request->boolean('active'))))
            ->orderBy('preferred_name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.crew.index', [
            'crewProfiles' => $crewProfiles,
            'filters' => $request->only(['search', 'active']),
            'roles' => CrewRole::query()->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('manageCrew');

        return view('admin.crew.create', $this->formData());
    }

    public function store(InviteCrewMemberRequest $request, InviteCrewMember $inviteCrewMember): RedirectResponse
    {
        $crewProfile = $inviteCrewMember->execute($request->validated());

        return redirect()->route('admin.crew.index')->with('status', $crewProfile->user->invitation_sent_at
            ? "Crew member created and invitation sent to {$crewProfile->user->email}."
            : 'Crew member created without sending an invitation. You can add their existing details and invite them later.');
    }

    public function invite(CrewProfile $crewProfile, InviteCrewMember $inviteCrewMember): RedirectResponse
    {
        Gate::authorize('manageCrew');
        abort_unless($crewProfile->user->is_active, 422, 'Inactive crew members cannot be invited.');

        $inviteCrewMember->send($crewProfile);

        return back()->with('status', "Invitation sent to {$crewProfile->user->email}.");
    }

    public function edit(CrewProfile $crewProfile): View
    {
        Gate::authorize('manageCrew');

        $crewProfile->load(['user', 'vehicles', 'roleQualifications', 'contractSignatures.events', 'contractSignatures.recordedBy']);

        return view('admin.crew.edit', compact('crewProfile') + $this->formData() + [
            'contracts' => CrewContract::query()->orderByDesc('effective_from')->orderBy('name')->get(),
        ]);
    }

    public function update(SaveCrewProfileRequest $request, CrewProfile $crewProfile, SaveCrewProfile $saveCrewProfile): RedirectResponse
    {
        $saveCrewProfile->execute($request->validated(), $crewProfile);

        return back()->with('status', 'Crew member updated.');
    }

    private function formData(): array
    {
        return [
            'roles' => CrewRole::query()->where('is_active', true)->orderBy('name')->get(),
            'qualificationStatuses' => CrewRoleQualificationStatus::cases(),
        ];
    }
}
