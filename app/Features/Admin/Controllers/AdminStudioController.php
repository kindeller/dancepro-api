<?php

namespace App\Features\Admin\Controllers;

use App\Features\Admin\Requests\SaveStudioAccountAccessRequest;
use App\Features\Admin\Requests\SaveStudioGalleryCodeRequest;
use App\Features\Admin\Requests\SaveStudioRequest;
use App\Features\Admin\Requests\UpdateStudioStatusRequest;
use App\Features\Studios\Actions\ManageStudioAccountAccess;
use App\Features\Studios\Actions\SaveStudio;
use App\Features\Studios\Actions\SaveStudioGalleryCode;
use App\Features\Studios\Models\Studio;
use App\Features\Studios\Models\StudioAccountAccess;
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

        $studioQuery = Studio::query()
            ->with('contacts')
            ->withCount('concerts')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(fn ($query) => $query->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhereHas('contacts', fn ($query) => $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('role', 'like', "%{$search}%")
                        ->orWhere('emails', 'like', "%{$search}%")));
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()));

        $statusCounts = (clone $studioQuery)
            ->select('status')
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $studios = $studioQuery
            ->orderByRaw("case status when 'active' then 0 when 'inactive' then 1 else 2 end")
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.studios.index', [
            'studios' => $studios,
            'statusCounts' => $statusCounts,
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
        $studio = $saveStudio->execute($request->validated(), actor: $request->user());

        return redirect()->route('admin.studios.edit', $studio)->with('status', 'Studio created.');
    }

    public function edit(Studio $studio): View
    {
        Gate::authorize('manageStudios');

        $studio->load(['contacts.user', 'accountAccesses.user', 'concerts' => fn ($query) => $query
            ->withCount('mediaCollections')
            ->orderByDesc('event_date')
            ->orderBy('name')]);

        return view('admin.studios.edit', compact('studio') + ['statuses' => StudioStatus::cases()]);
    }

    public function inviteAccount(SaveStudioAccountAccessRequest $request, Studio $studio, ManageStudioAccountAccess $access): RedirectResponse
    {
        $record = $access->invite($studio, $request->user(), $request->validated());

        return back()->with('status', $record->status === 'active' ? 'Existing account linked to the studio.' : 'Studio account invitation sent.');
    }

    public function revokeAccount(Request $request, Studio $studio, StudioAccountAccess $accountAccess, ManageStudioAccountAccess $access): RedirectResponse
    {
        Gate::authorize('manageStudios');
        abort_unless($accountAccess->studio_id === $studio->id, 404);
        $access->revoke($accountAccess);

        return back()->with('status', 'Studio account access revoked. Customer media and purchases are unchanged.');
    }

    public function update(SaveStudioRequest $request, Studio $studio, SaveStudio $saveStudio): RedirectResponse
    {
        $saveStudio->execute($request->validated(), $studio, $request->user());

        return back()->with('status', 'Studio updated.');
    }

    public function saveGalleryCode(SaveStudioGalleryCodeRequest $request, Studio $studio, SaveStudioGalleryCode $save): RedirectResponse
    {
        $save->execute($studio, $request->user(), $request->validated('gallery_code'), (int) $request->validated('version'));

        return back()->with('status', 'Studio gallery code saved.');
    }

    public function updateStatus(UpdateStudioStatusRequest $request, Studio $studio): RedirectResponse
    {
        $studio->update(['status' => $request->validated('status')]);

        return back()->with('status', 'Studio status updated.');
    }
}
