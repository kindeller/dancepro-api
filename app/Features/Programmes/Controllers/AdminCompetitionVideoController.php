<?php

namespace App\Features\Programmes\Controllers;

use App\Features\Media\Models\MediaCollection;
use App\Features\Programmes\Models\CompetitionVideoBatch;
use App\Features\Programmes\Models\CompetitionVideoFile;
use App\Features\Programmes\Models\CompetitionVideoHold;
use App\Features\Programmes\Models\Programme;
use App\Features\Programmes\Models\ProgrammeUploadPlan;
use App\Features\Programmes\Requests\AdminCompetitionVideoRequest;
use App\Features\Programmes\Requests\AssignCompetitionVideoRequest;
use App\Features\Programmes\Requests\UpdateCompetitionVideoHoldRequest;
use App\Features\Programmes\Services\CompetitionProgrammeNodes;
use App\Features\Programmes\Services\CompetitionVideoHolds;
use App\Features\Programmes\Services\CompetitionVideoUploads;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class AdminCompetitionVideoController extends Controller
{
    public function events(AdminCompetitionVideoRequest $request): RedirectResponse
    {
        return to_route('admin.competition.objects.index');
    }

    public function index(AdminCompetitionVideoRequest $request, SchedulingEvent $schedulingEvent, CompetitionProgrammeNodes $nodes): Response
    {
        abort_unless($schedulingEvent->event_type->value === 'competition', 404);
        $programme = Programme::where('scheduling_event_id', $schedulingEvent->id)->firstOrFail();
        $plan = ProgrammeUploadPlan::where('programme_id', $programme->id)->latest('id')->firstOrFail();
        $items = collect($nodes->nodes($plan))->filter(fn (array $node) => ($node['kind'] ?? null) === 'item'
            && ($node['merged_into_uuid'] ?? null) === null)->values();
        $batches = CompetitionVideoBatch::where('scheduling_event_id', $schedulingEvent->id)->with('files')->latest()->get();
        $holds = CompetitionVideoHold::where('scheduling_event_id', $schedulingEvent->id)->where('is_held', true)->pluck('item_uuid')->all();
        $collections = MediaCollection::where('competition_id', $schedulingEvent->id)->where('media_type', 'video')
            ->withCount('assets')->get()->keyBy(fn ($collection) => $collection->metadata['item_uuid'] ?? '');
        $history = DB::table('competition_video_hold_changes')->where('scheduling_event_id', $schedulingEvent->id)->latest('id')->limit(20)->get();

        return response()->view('admin.programmes.competition-videos', compact('schedulingEvent', 'items', 'batches', 'holds', 'collections', 'history'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function assign(AssignCompetitionVideoRequest $request, SchedulingEvent $schedulingEvent, CompetitionVideoFile $file, CompetitionVideoUploads $uploads): RedirectResponse
    {
        $uploads->assign($schedulingEvent, $file, $request->validated('item_uuid'));

        return back()->with('status', 'Video assigned. Complete its batch from the uploader when every file is verified.');
    }

    public function hold(UpdateCompetitionVideoHoldRequest $request, SchedulingEvent $schedulingEvent, CompetitionVideoHolds $holds): RedirectResponse
    {
        $holds->set($schedulingEvent, $request->user(), $request->validated('item_uuid'), $request->boolean('is_held'));

        return back()->with('status', $request->boolean('is_held') ? 'Routine video placed on HOLD.' : 'Routine video unheld.');
    }
}
