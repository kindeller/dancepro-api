<?php

namespace App\Features\Admin\Controllers;

use App\Features\Admin\Requests\SaveStudioRequest;
use App\Features\Studios\Actions\SaveStudio;
use App\Features\Studios\Models\Studio;
use App\Features\Studios\Support\StudioStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AdminStudioController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('manageStudios');

        $studios = Studio::query()
            ->withCount('concerts')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('contact_email', 'like', "%{$search}%"));
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.studios.index', [
            'studios' => $studios,
            'statuses' => StudioStatus::cases(),
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('manageStudios');

        return view('admin.studios.create', ['statuses' => StudioStatus::cases()]);
    }

    public function store(SaveStudioRequest $request, SaveStudio $saveStudio): RedirectResponse
    {
        $studio = $saveStudio->execute($request->validated());

        return redirect()->route('admin.studios.edit', $studio)->with('status', 'Studio created.');
    }

    public function edit(Studio $studio): View
    {
        Gate::authorize('manageStudios');

        $studio->load(['concerts' => fn ($query) => $query
            ->withCount('mediaCollections')
            ->orderByDesc('event_date')
            ->orderBy('name')]);

        return view('admin.studios.edit', compact('studio') + ['statuses' => StudioStatus::cases()]);
    }

    public function update(SaveStudioRequest $request, Studio $studio, SaveStudio $saveStudio): RedirectResponse
    {
        $saveStudio->execute($request->validated(), $studio);

        return back()->with('status', 'Studio updated.');
    }
}
