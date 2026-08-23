<?php

namespace App\Features\Admin\Controllers;

use App\Features\Admin\Requests\SaveConcertRequest;
use App\Features\Concerts\Actions\SaveConcert;
use App\Features\Concerts\Models\Concert;
use App\Features\Concerts\Support\ConcertStatus;
use App\Features\Studios\Models\Studio;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AdminConcertController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('manageConcerts');

        $concerts = Concert::query()
            ->with('studio')
            ->withCount('mediaCollections')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('venue_name', 'like', "%{$search}%"));
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('studio_id'), fn ($query) => $query->where('studio_id', $request->integer('studio_id')))
            ->orderByDesc('event_date')
            ->paginate(25)
            ->withQueryString();

        return view('admin.concerts.index', [
            'concerts' => $concerts,
            'studios' => Studio::query()->orderBy('name')->get(),
            'statuses' => ConcertStatus::cases(),
            'filters' => $request->only(['search', 'status', 'studio_id']),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('manageConcerts');

        return view('admin.concerts.create', $this->formData() + [
            'selectedStudioId' => request()->integer('studio_id') ?: null,
        ]);
    }

    public function store(SaveConcertRequest $request, SaveConcert $saveConcert): RedirectResponse
    {
        /** @var User $staff */
        $staff = $request->user();
        $concert = $saveConcert->execute($request->validated(), $staff);

        return redirect()->route('admin.concerts.edit', $concert)->with('status', 'Concert created.');
    }

    public function edit(Concert $concert): View
    {
        Gate::authorize('manageConcerts');

        return view('admin.concerts.edit', compact('concert') + $this->formData());
    }

    public function update(SaveConcertRequest $request, Concert $concert, SaveConcert $saveConcert): RedirectResponse
    {
        /** @var User $staff */
        $staff = $request->user();
        $saveConcert->execute($request->validated(), $staff, $concert);

        return back()->with('status', 'Concert updated.');
    }

    private function formData(): array
    {
        return [
            'studios' => Studio::query()->orderBy('name')->get(),
            'statuses' => ConcertStatus::cases(),
        ];
    }
}
