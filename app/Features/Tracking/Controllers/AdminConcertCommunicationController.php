<?php

namespace App\Features\Tracking\Controllers;

use App\Features\Customers\Services\StudioCommunicationHistory;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use App\Features\Tracking\Models\ConcertTrackerEmailDelivery;
use App\Features\Tracking\Models\ConcertTrackerEmailTemplate;
use App\Features\Tracking\Models\StudioConversationAttachment;
use App\Features\Tracking\Requests\AssignStudioConversationRequest;
use App\Features\Tracking\Requests\SendStudioConversationMessageRequest;
use App\Features\Tracking\Requests\UpdateConcertTrackerEmailTemplateRequest;
use App\Features\Tracking\Services\ConcertTrackerCommunications;
use App\Features\Tracking\Services\ConcertTrackerEmails;
use App\Features\Tracking\Services\EmailBranding;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminConcertCommunicationController extends Controller
{
    public function inbox(StudioCommunicationHistory $history): RedirectResponse
    {
        return redirect()->route('admin.messages.index', ['channel' => 'studio']);
    }

    public function event(SchedulingEvent $schedulingEvent, ConcertTrackerCommunications $communications): Response
    {
        return response()->view('admin.tracking.concert-communications', $communications->eventPage($schedulingEvent))
            ->header('Cache-Control', 'private, no-store');
    }

    public function templates(Request $request, ConcertTrackerCommunications $communications, EmailBranding $branding): Response
    {
        return response()->view('admin.tracking.concert-email-templates', $communications->templatesPage() + [
            'emailBranding' => $branding->for($request->user()->id),
        ])
            ->header('Cache-Control', 'private, no-store');
    }

    public function studio(Studio $studio, StudioCommunicationHistory $history): RedirectResponse
    {
        return redirect()->route('admin.messages.index', ['channel' => 'studio', 'thread' => $studio->uuid]);
    }

    public function reply(SendStudioConversationMessageRequest $request, Studio $studio, StudioCommunicationHistory $history): RedirectResponse
    {
        $history->adminReply($request->user(), $studio, $request->validated());

        return back()->with('status', 'Reply added to the studio thread and emailed to the contact.');
    }

    public function assign(AssignStudioConversationRequest $request, Studio $studio, StudioCommunicationHistory $history): RedirectResponse
    {
        $history->assign($studio, $request->validated('assigned_user_id'));

        return back()->with('status', 'Studio conversation owner updated.');
    }

    public function attachment(StudioConversationAttachment $attachment): StreamedResponse
    {
        return Storage::disk($attachment->disk)->download($attachment->storage_key, $attachment->original_name, ['Cache-Control' => 'private, no-store']);
    }

    public function updateTemplate(UpdateConcertTrackerEmailTemplateRequest $request, ConcertTrackerEmailTemplate $template): RedirectResponse
    {
        $input = $request->validated();
        $input['is_active'] = $request->boolean('is_active');
        $template->update($input);

        return back()->with('status', 'Email template updated.');
    }

    public function cancel(Request $request, ConcertTrackerEmailDelivery $delivery, ConcertTrackerEmails $emails): RedirectResponse
    {
        $emails->cancel($delivery, $request->user());

        return back()->with('status', 'Scheduled email cancelled.');
    }

    public function resend(Request $request, ConcertTrackerEmailDelivery $delivery, ConcertTrackerEmails $emails): RedirectResponse
    {
        $emails->resend($delivery, $request->user());

        return back()->with('status', 'Email queued again.');
    }
}
