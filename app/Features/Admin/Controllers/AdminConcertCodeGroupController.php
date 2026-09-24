<?php

namespace App\Features\Admin\Controllers;

use App\Features\Admin\Requests\SaveConcertCodeGroupRequest;
use App\Features\Concerts\Actions\ManageConcertCodeGroup;
use App\Features\Concerts\Models\Concert;
use App\Features\Concerts\Models\ConcertCodeGroup;
use App\Features\Studios\Models\Studio;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AdminConcertCodeGroupController extends Controller
{
    public function index(Studio $studio): View
    {
        Gate::authorize('manageConcerts');

        return view('admin.concerts.code-groups', [
            'studio' => $studio,
            'groups' => ConcertCodeGroup::where('studio_id', $studio->id)->with('concerts', 'changes.actor')->orderByDesc('year')->get(),
            'availableConcerts' => Concert::where('studio_id', $studio->id)->whereNull('concert_code_group_id')->whereNotNull('event_date')->orderByDesc('event_date')->get(),
        ]);
    }

    public function store(SaveConcertCodeGroupRequest $request, Studio $studio, ManageConcertCodeGroup $manage): RedirectResponse
    {
        $manage->create($studio, $request->user(), $request->validated());

        return back()->with('status', 'Concert video code group created. Release it when access should begin.');
    }

    public function concerts(SaveConcertCodeGroupRequest $request, Studio $studio, ConcertCodeGroup $group, ManageConcertCodeGroup $manage): RedirectResponse
    {
        $this->belongsTo($studio, $group);
        $manage->addConcerts($group, $request->user(), $request->validated());

        return back()->with('status', 'Concerts added to the group. Existing access periods were not restarted.');
    }

    public function release(SaveConcertCodeGroupRequest $request, Studio $studio, ConcertCodeGroup $group, ManageConcertCodeGroup $manage): RedirectResponse
    {
        $this->belongsTo($studio, $group);
        $manage->release($group, $request->user(), $request->validated());

        return back()->with('status', 'Concert video group released. Access periods have started.');
    }

    public function code(SaveConcertCodeGroupRequest $request, Studio $studio, ConcertCodeGroup $group, ManageConcertCodeGroup $manage): RedirectResponse
    {
        $this->belongsTo($studio, $group);
        $manage->rotate($group, $request->user(), $request->validated());

        return back()->with('status', 'Concert video code changed. Existing customer access was preserved.');
    }

    private function belongsTo(Studio $studio, ConcertCodeGroup $group): void
    {
        abort_unless($group->studio_id === $studio->id, 404);
    }
}
