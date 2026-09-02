<?php

namespace App\Features\Operations\Controllers;

use App\Features\Operations\Actions\PostEventMessage;
use App\Features\Operations\Models\EventMessage;
use App\Features\Operations\Models\EventMessageRead;
use App\Features\Operations\Requests\StoreEventMessageRequest;
use App\Features\Scheduling\Models\SchedulingShiftAssignment;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CrewEventCommunicationController extends Controller
{
    public function store(StoreEventMessageRequest $request, SchedulingShiftAssignment $assignment, PostEventMessage $postMessage): RedirectResponse
    {
        $this->authoriseAssignment($request, $assignment);
        abort_unless($request->input('message_type') === 'discussion', 403);
        $postMessage->execute(
            $assignment->shift->schedulingEvent,
            $request->user(),
            'discussion',
            $request->string('body')->toString() ?: null,
            $request->file('attachment'),
        );

        return back()->with('status', 'Message posted.');
    }

    public function acknowledge(Request $request, SchedulingShiftAssignment $assignment, EventMessage $message): RedirectResponse
    {
        $this->authoriseAssignment($request, $assignment);
        abort_unless($message->scheduling_event_id === $assignment->shift->scheduling_event_id && $message->isAnnouncement(), 404);
        EventMessageRead::query()->updateOrCreate(
            ['event_message_id' => $message->id, 'user_id' => $request->user()->id],
            ['read_at' => now()],
        );

        return back()->with('status', 'Announcement acknowledged.');
    }

    private function authoriseAssignment(Request $request, SchedulingShiftAssignment $assignment): void
    {
        $assignment->loadMissing('shift.schedulingEvent');
        abort_unless($assignment->crew_profile_id === $request->user()?->crewProfile?->id && $assignment->status === 'published', 403);
    }
}
