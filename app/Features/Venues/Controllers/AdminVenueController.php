<?php

namespace App\Features\Venues\Controllers;

use App\Features\Venues\Actions\CreateVenue;
use App\Features\Venues\Models\Venue;
use App\Features\Venues\Requests\StoreVenueRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AdminVenueController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('manageScheduling');

        $venues = Venue::query()
            ->withCount('schedulingEvents')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = '%'.$request->string('search')->toString().'%';
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', $search)
                        ->orWhere('address_line_1', 'like', $search)
                        ->orWhere('suburb', 'like', $search);
                });
            })
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.venues.index', [
            'venues' => $venues,
            'search' => $request->string('search')->toString(),
        ]);
    }

    public function store(StoreVenueRequest $request, CreateVenue $createVenue): RedirectResponse
    {
        $createVenue->execute($request->validated());

        return redirect()->route('admin.venues.index')->with('status', 'Venue added.');
    }
}
