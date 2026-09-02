<?php

namespace App\Features\Crew\Controllers;

use App\Features\Crew\Actions\CreateCrewContract;
use App\Features\Crew\Models\CrewContract;
use App\Features\Crew\Requests\StoreCrewContractRequest;
use App\Features\Crew\Support\CrewContractStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AdminCrewContractController extends Controller
{
    public function index(): View
    {
        Gate::authorize('manageCrew');

        return view('admin.crew-contracts.index', [
            'contracts' => CrewContract::query()->withCount('signatures')->orderByDesc('effective_from')->paginate(25),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('manageCrew');

        return $this->contractForm();
    }

    public function show(CrewContract $crewContract): View
    {
        Gate::authorize('manageCrew');

        $crewContract->load(['createdBy'])->loadCount('signatures');

        return view('admin.crew-contracts.show', ['contract' => $crewContract]);
    }

    public function duplicate(CrewContract $crewContract): View
    {
        Gate::authorize('manageCrew');

        return $this->contractForm($crewContract);
    }

    public function store(StoreCrewContractRequest $request, CreateCrewContract $createCrewContract): RedirectResponse
    {
        /** @var User $staff */
        $staff = $request->user();
        $createCrewContract->execute($request->validated(), $staff);

        return redirect()->route('admin.crew-contracts.index')->with('status', 'Contract version created.');
    }

    private function contractForm(?CrewContract $sourceContract = null): View
    {
        return view('admin.crew-contracts.create', [
            'statuses' => CrewContractStatus::cases(),
            'sourceContract' => $sourceContract,
        ]);
    }
}
