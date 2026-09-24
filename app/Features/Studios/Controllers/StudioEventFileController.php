<?php

namespace App\Features\Studios\Controllers;

use App\Features\Customers\Services\StudioCommunicationHistory;
use App\Features\Operations\Services\OperationsFileStorage;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Actions\StoreStudioEventFiles;
use App\Features\Studios\Models\Studio;
use App\Features\Studios\Models\StudioEventFile;
use App\Features\Studios\Requests\ReviewStudioEventFileRequest;
use App\Features\Studios\Requests\UploadStudioEventFilesRequest;
use App\Features\Tracking\Models\ConcertTrackerTask;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudioEventFileController extends Controller
{
    public function store(UploadStudioEventFilesRequest $request, Studio $studio, SchedulingEvent $schedulingEvent, StudioCommunicationHistory $history, StoreStudioEventFiles $store): RedirectResponse|JsonResponse
    {
        abort_unless($history->canView($request->user(), $studio) && $schedulingEvent->studio_id === $studio->id, 403);
        $store->execute($schedulingEvent, $request->user(), $request->string('kind')->toString(), $request->file('files'));
        $message = $request->input('kind') === 'programme' ? 'Programme submitted.' : count($request->file('files')).' studio video file(s) submitted.';

        return $request->expectsJson()
            ? response()->json(['success' => true, 'message' => $message, 'data' => []], 201)
            : back()->with('status', $message);
    }

    public function download(Request $request, StudioEventFile $studioEventFile, OperationsFileStorage $storage, StudioCommunicationHistory $history): StreamedResponse
    {
        $file = $studioEventFile->load('event.studio');
        abort_unless($request->user()->canAccessAdmin() || $history->canView($request->user(), $file->event->studio), 403);
        abort_unless($storage->disk()->exists($file->storage_key), 404);

        return $storage->disk()->download($file->storage_key, $file->original_name, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function review(ReviewStudioEventFileRequest $request, StudioEventFile $studioEventFile): RedirectResponse
    {
        $studioEventFile->update($request->validated() + ['reviewed_by_user_id' => $request->user()->id, 'reviewed_at' => now()]);
        if ($studioEventFile->kind === 'studio_video') {
            $pending = StudioEventFile::where('scheduling_event_id', $studioEventFile->scheduling_event_id)->where('kind', 'studio_video')->where('is_current', true)->where('status', '!=', 'approved')->exists();
            if (! $pending) {
                ConcertTrackerTask::updateOrCreate(
                    ['scope_type' => 'event', 'scope_id' => $studioEventFile->scheduling_event_id, 'task_key' => 'slideshow_received'],
                    ['status' => 'complete', 'completed_by_user_id' => $request->user()->id, 'completed_at' => now()],
                );
            }
        }

        return back()->with('status', 'Studio file review updated.');
    }
}
