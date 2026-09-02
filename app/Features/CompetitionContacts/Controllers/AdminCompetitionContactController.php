<?php

namespace App\Features\CompetitionContacts\Controllers;

use App\Features\CompetitionContacts\Actions\SaveCompetitionContact;
use App\Features\CompetitionContacts\Models\CompetitionContact;
use App\Features\CompetitionContacts\Requests\SaveCompetitionContactRequest;
use App\Features\CompetitionContacts\Requests\UpdateCompetitionContactStatusRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AdminCompetitionContactController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('manageScheduling');
        $contactQuery = CompetitionContact::query()->with('staff')->withCount('events')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(fn ($query) => $query->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhereHas('staff', fn ($query) => $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('role', 'like', "%{$search}%")
                        ->orWhere('emails', 'like', "%{$search}%")));
            })
            ->when($request->filled('status'), fn ($query) => $query->where('is_active', $request->string('status')->toString() === 'active'));

        $statusCounts = (clone $contactQuery)
            ->select('is_active')
            ->selectRaw('is_active, count(*) as aggregate')
            ->groupBy('is_active')
            ->pluck('aggregate', 'is_active');

        $contacts = $contactQuery
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.competition-contacts.index', compact('contacts', 'statusCounts') + ['filters' => $request->only(['search', 'status'])]);
    }

    public function create(): View
    {
        Gate::authorize('manageScheduling');

        return view('admin.competition-contacts.create');
    }

    public function store(SaveCompetitionContactRequest $request, SaveCompetitionContact $save): RedirectResponse
    {
        $contact = $save->execute($request->validated());

        return redirect()->route('admin.competition-contacts.edit', $contact)->with('status', 'Competition contact created.');
    }

    public function edit(CompetitionContact $competitionContact): View
    {
        Gate::authorize('manageScheduling');

        return view('admin.competition-contacts.edit', ['contact' => $competitionContact->load('staff')]);
    }

    public function update(SaveCompetitionContactRequest $request, CompetitionContact $competitionContact, SaveCompetitionContact $save): RedirectResponse
    {
        $save->execute($request->validated(), $competitionContact);

        return back()->with('status', 'Competition contact updated.');
    }

    public function updateStatus(UpdateCompetitionContactStatusRequest $request, CompetitionContact $competitionContact): RedirectResponse
    {
        $competitionContact->update(['is_active' => $request->validated('is_active')]);

        return back()->with('status', 'Competition contact status updated.');
    }
}
