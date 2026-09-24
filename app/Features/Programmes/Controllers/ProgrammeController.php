<?php

namespace App\Features\Programmes\Controllers;

use App\Features\Programmes\Actions\ConfirmProgramme;
use App\Features\Programmes\Actions\PrepareProgramme;
use App\Features\Programmes\Actions\ReplaceProgrammeSource;
use App\Features\Programmes\Actions\SaveProgrammeDraft;
use App\Features\Programmes\Models\ProgrammeRevision;
use App\Features\Programmes\Models\ProgrammeSource;
use App\Features\Programmes\Requests\ListProgrammeEventsRequest;
use App\Features\Programmes\Requests\ProgrammeVersionRequest;
use App\Features\Programmes\Requests\SaveProgrammeDraftRequest;
use App\Features\Programmes\Requests\StartProgrammeRequest;
use App\Features\Programmes\Requests\UploadProgrammeSourceRequest;
use App\Features\Programmes\Services\ListProgrammeEvents;
use App\Features\Programmes\Services\ProgrammePreparation;
use App\Features\Programmes\Services\ProgrammeSourceStorage;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Http\Controllers\Controller;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProgrammeController extends Controller
{
    public function __construct(private readonly ProgrammePreparation $preparation) {}

    public function events(ListProgrammeEventsRequest $request, ListProgrammeEvents $list): JsonResponse
    {
        $page = $list->execute($request->validated('search'), $request->validated('from'), $request->integer('per_page', 50), $request->user());

        return ApiResponse::success('Approved events.', collect($page->items())->map($list->data(...))->all(), meta: [
            'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function show(SchedulingEvent $schedulingEvent): View
    {
        Gate::authorize('viewProgramme', $schedulingEvent);

        return view('admin.programmes.show', $this->preparation->editor($schedulingEvent));
    }

    public function start(StartProgrammeRequest $request, SchedulingEvent $schedulingEvent, PrepareProgramme $prepare): RedirectResponse
    {
        Gate::authorize('updateProgramme', $schedulingEvent);
        $prepare->execute($schedulingEvent, $request->user(), $request->file('programme'));

        return to_route('admin.programmes.show', $schedulingEvent);
    }

    public function extraction(SchedulingEvent $schedulingEvent): JsonResponse
    {
        Gate::authorize('viewProgramme', $schedulingEvent);
        $editor = $this->preparation->editor($schedulingEvent);

        return ApiResponse::success('', [
            'status' => $editor['extraction']?->status,
            'message' => $editor['extraction']?->message,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function save(SaveProgrammeDraftRequest $request, SchedulingEvent $schedulingEvent, SaveProgrammeDraft $save): JsonResponse
    {
        $programme = $save->execute($this->preparation->forEvent($schedulingEvent), $request->user(), $request->integer('version'), $request->validated('nodes'));

        return ApiResponse::success('Draft saved.', ['version' => $programme->draft_version, 'nodes' => $programme->draftNodes()]);
    }

    public function confirm(ProgrammeVersionRequest $request, SchedulingEvent $schedulingEvent, ConfirmProgramme $confirm): JsonResponse
    {
        $revision = $confirm->execute($this->preparation->forEvent($schedulingEvent), $request->user(), $request->integer('version'));

        return ApiResponse::success('Programme confirmed.', ['version' => $revision->version, 'confirmed_at' => $revision->confirmed_at->toIso8601String()]);
    }

    public function upload(UploadProgrammeSourceRequest $request, SchedulingEvent $schedulingEvent, ReplaceProgrammeSource $replace): RedirectResponse
    {
        $replace->execute($this->preparation->forEvent($schedulingEvent), $request->user(), $request->integer('version'), $request->file('programme'));

        return to_route('admin.programmes.show', $schedulingEvent)->with('status', 'Source PDF saved. Review the draft before confirming.');
    }

    public function source(SchedulingEvent $schedulingEvent, ProgrammeSource $source, ProgrammeSourceStorage $storage): StreamedResponse
    {
        Gate::authorize('viewProgramme', $schedulingEvent);

        return $storage->respond($this->preparation->source($schedulingEvent, $source));
    }

    public function confirmedSource(SchedulingEvent $schedulingEvent, ProgrammeSourceStorage $storage, ?ProgrammeRevision $revision = null): StreamedResponse
    {
        Gate::authorize('viewProgramme', $schedulingEvent);
        abort_if($schedulingEvent->cancelled_at !== null, 409, 'This event is cancelled.');
        $source = $this->preparation->revision($schedulingEvent, $revision)->source;
        abort_unless($source, 404);

        return $storage->respond($source);
    }

    public function snapshot(SchedulingEvent $schedulingEvent, ?ProgrammeRevision $revision = null): JsonResponse
    {
        Gate::authorize('viewProgramme', $schedulingEvent);
        abort_if($schedulingEvent->cancelled_at !== null, 409, 'This event is cancelled.');
        $revision = $this->preparation->revision($schedulingEvent, $revision);

        return ApiResponse::success('Confirmed programme.', $revision->snapshot + [
            'revision_uuid' => $revision->uuid,
            'confirmed_at' => $revision->confirmed_at->toIso8601String(),
            'source_url' => $revision->programme_source_id ? route('staff.programmes.source', [$schedulingEvent, $revision], false) : null,
        ])
            ->header('Cache-Control', 'private, no-store');
    }
}
