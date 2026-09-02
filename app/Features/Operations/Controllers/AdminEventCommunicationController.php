<?php

namespace App\Features\Operations\Controllers;

use App\Features\Operations\Actions\PostEventMessage;
use App\Features\Operations\Requests\StoreEventMessageRequest;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class AdminEventCommunicationController extends Controller
{
    public function store(StoreEventMessageRequest $request, SchedulingEvent $schedulingEvent, PostEventMessage $postMessage): RedirectResponse
    {
        Gate::authorize('manageScheduling');
        $postMessage->execute(
            $schedulingEvent,
            $request->user(),
            $request->string('message_type')->toString(),
            $request->string('body')->toString() ?: null,
            $request->file('attachment'),
        );

        return back()->with('status', $request->input('message_type') === 'announcement' ? 'Announcement posted.' : 'Message posted.');
    }
}
