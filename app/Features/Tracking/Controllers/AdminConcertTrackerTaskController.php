<?php

namespace App\Features\Tracking\Controllers;

use App\Features\Concerts\Services\ConcertDirectorReviews;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Scheduling\Services\EventAvailability;
use App\Features\Tracking\Actions\UpdateConcertTrackerTask;
use App\Features\Tracking\Models\ConcertTrackerEmailTemplate;
use App\Features\Tracking\Requests\ConcertTrackerRequest;
use App\Features\Tracking\Requests\SendConcertTrackerEmailRequest;
use App\Features\Tracking\Requests\UpdateConcertTrackerEmailTemplateRequest;
use App\Features\Tracking\Requests\UpdateConcertTrackerTaskRequest;
use App\Features\Tracking\Services\BookingTrackerBar;
use App\Features\Tracking\Services\ConcertEditingDownloads;
use App\Features\Tracking\Services\ConcertQualityChecklist;
use App\Features\Tracking\Services\ConcertTracker;
use App\Features\Tracking\Services\ConcertTrackerEmails;
use App\Features\Tracking\Services\ConcertVideoReadiness;
use App\Features\Tracking\Services\EmailBranding;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class AdminConcertTrackerTaskController extends Controller
{
    public function show(ConcertTrackerRequest $request, SchedulingEvent $schedulingEvent, string $taskKey, ConcertTracker $tracker, EmailBranding $branding, BookingTrackerBar $bar): Response|RedirectResponse
    {
        abort_unless($schedulingEvent->event_type->value === 'concert', 404);
        if ($taskKey === 'payment_received') {
            return to_route('admin.concert-tracker.tasks.show', [$schedulingEvent, 'invoice_sent']);
        }
        if ($taskKey === 'invoice_sent') {
            $booking = $schedulingEvent->concertBookingItem?->booking;
            if (! $booking) {
                $detail = $tracker->detail($schedulingEvent, $taskKey);

                return response()->view('admin.tracking.task-unavailable', $detail + [
                    'trackerBar' => $bar->sourcePage($detail, $request->session()->get('concert_tracker_context', [])),
                    'blockers' => ['Link this event to an approved studio booking before preparing its invoice.'],
                ]);
            }

            return to_route('admin.concert-bookings.show', [$booking, 'tab' => 'invoice']);
        }
        if ($taskKey === 'director_approval') {
            return to_route('admin.concert-tracker.tasks.show', [$schedulingEvent, 'final_release']);
        }
        if (in_array($taskKey, ['director_corrections', 'photo_release', 'final_release'], true)) {
            $detail = $tracker->detail($schedulingEvent, $taskKey);
            $review = in_array($taskKey, ['director_corrections', 'final_release'], true) ? app(ConcertDirectorReviews::class)->page($schedulingEvent) : null;
            $email = $taskKey === 'director_corrections' && $review && $request->boolean('email') && $review['checksReady'];

            return response()->view($email ? 'admin.tracking.task-email' : 'admin.tracking.'.match ($taskKey) {
                'director_corrections' => 'director-corrections', 'photo_release' => 'photo-release', default => 'final-release',
            }, $detail + [
                'trackerBar' => $bar->releasePage($detail, $request->session()->get('concert_tracker_context', [])),
                'review' => $review, 'emailBranding' => $branding->for($request->user()->id),
                'emailBlockers' => $taskKey === 'director_corrections' ? app(ConcertDirectorReviews::class)->emailBlockers($review, $taskKey) : [],
            ])->header('Cache-Control', 'private, no-store');
        }
        if ($taskKey === 'video_uploaded') {
            $detail = $tracker->detail($schedulingEvent, $taskKey);

            return response()->view('admin.tracking.concert-video-uploads', $detail + [
                'trackerBar' => $bar->videoUploadsPage($detail, $request->session()->get('concert_tracker_context', [])),
                'uploads' => app(ConcertVideoReadiness::class)->page($schedulingEvent),
            ])->header('Cache-Control', 'private, no-store');
        }
        if ($taskKey === 'quality_check') {
            $detail = $tracker->detail($schedulingEvent, $taskKey);

            return response()->view('admin.tracking.concert-quality', $detail + [
                'trackerBar' => $bar->qualityPage($detail, $request->session()->get('concert_tracker_context', [])),
                'quality' => app(ConcertQualityChecklist::class)->page($detail['task']),
            ])->header('Cache-Control', 'private, no-store');
        }
        if ($taskKey === 'concert_edited') {
            $detail = $tracker->detail($schedulingEvent, $taskKey);

            return response()->view('admin.tracking.concert-editing', $detail + [
                'trackerBar' => $bar->editingPage($detail, $request->session()->get('concert_tracker_context', [])),
                'editingLayout' => 'layouts.admin',
                'editingDownloads' => app(ConcertEditingDownloads::class)->page($schedulingEvent, $request->user()),
            ])->header('Cache-Control', 'private, no-store');
        }
        if ($taskKey === 'crew_ready') {
            $detail = $tracker->detail($schedulingEvent, $taskKey);
            $studio = $schedulingEvent->studio;

            return response()->view('admin.tracking.crew-availability', $detail + app(EventAvailability::class)->page($studio ? ['studio' => $studio->uuid] : [], $studio ? null : $schedulingEvent) + [
                'trackerBar' => $bar->sourcePage($detail, $request->session()->get('concert_tracker_context', [])),
            ])->header('Cache-Control', 'private, no-store');
        }
        if ($taskKey === 'slideshow_received' && $schedulingEvent->concertBookingItem?->booking) {
            return redirect()->to(route('admin.concert-bookings.show', [$schedulingEvent->concertBookingItem->booking, 'tab' => 'studio-media', 'event' => $schedulingEvent->uuid]));
        }
        if ($taskKey === 'video_titles') {
            return redirect()->route('admin.concert-title-cards.show', $schedulingEvent);
        }
        if ($taskKey === 'photos_uploaded') {
            return redirect()->route('admin.photo-uploads.show', $schedulingEvent);
        }
        if ($taskKey === 'programme_prepared') {
            return redirect()->route('admin.programmes.show', $schedulingEvent);
        }
        if ($taskKey === 'booking_received' && $schedulingEvent->concertBookingItem?->booking) {
            return redirect()->route('admin.concert-bookings.show', $schedulingEvent->concertBookingItem->booking);
        }

        if ($taskKey === 'confirmation_information_email' && $schedulingEvent->concertBookingItem?->booking) {
            return redirect()->route('admin.concert-bookings.confirmation', $schedulingEvent->concertBookingItem->booking);
        }

        if ($taskKey === 'final_numbers_received' && $schedulingEvent->concertBookingItem?->booking) {
            return redirect()->to(route('admin.concert-bookings.show', [$schedulingEvent->concertBookingItem->booking, 'tab' => 'seats-families']));
        }
        if (in_array($taskKey, ['final_numbers_email', 'concert_week_email', 'post_concert_email', 'approval_link_email'], true)) {
            $detail = $tracker->detail($schedulingEvent, $taskKey);

            return response()->view('admin.tracking.task-email', $detail + [
                'trackerBar' => $bar->emailPage($detail, $request->session()->get('concert_tracker_context', [])),
                'emailBranding' => $branding->for($request->user()->id),
                'emailBlockers' => $taskKey === 'approval_link_email'
                    ? app(ConcertDirectorReviews::class)->emailBlockers(app(ConcertDirectorReviews::class)->page($schedulingEvent), $taskKey) : [],
            ])->header('Cache-Control', 'private, no-store');
        }

        $detail = $tracker->detail($schedulingEvent, $taskKey);

        return response()->view('admin.tracking.task-unavailable', $detail + [
            'trackerBar' => array_replace($bar->sourcePage($detail, $request->session()->get('concert_tracker_context', [])), [
                'lockedPanels' => ['status', 'due', 'assignment', 'follow_up'],
            ]),
            'blockers' => ['Link this event to its studio booking before completing this booking task.'],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function update(UpdateConcertTrackerTaskRequest $request, SchedulingEvent $schedulingEvent, string $taskKey, UpdateConcertTrackerTask $tasks): RedirectResponse
    {
        $tasks->execute($schedulingEvent, $taskKey, $request->user(), $request->validated());

        return back()->with('status', 'Tracker task updated.');
    }

    public function updateTemplate(UpdateConcertTrackerEmailTemplateRequest $request, SchedulingEvent $schedulingEvent, string $taskKey, ConcertTracker $tracker): RedirectResponse
    {
        $data = $tracker->detail($schedulingEvent, $taskKey);
        abort_unless($data['template'], 404);
        $input = $request->validated();
        $input['is_active'] = $request->boolean('is_active');
        $data['template']->update($input);

        return back()->with('status', 'Email template updated.');
    }

    public function send(SendConcertTrackerEmailRequest $request, SchedulingEvent $schedulingEvent, string $taskKey, ConcertTracker $tracker, ConcertTrackerEmails $emails): RedirectResponse
    {
        $data = $tracker->detail($schedulingEvent, $taskKey);
        abort_unless($data['template'] instanceof ConcertTrackerEmailTemplate, 404);
        $delivery = $emails->queue($schedulingEvent, $data['task'], $data['template'], $request->user(), $request->validated());

        if ($taskKey === 'slideshow_received') {
            return back()->with('status', 'Studio file request '.$delivery->status.'. Receipt is completed after the files are approved.');
        }

        return back()->with('status', match ($delivery->status) {
            'sent' => $delivery->is_simulated ? 'Test email captured in the local inbox.' : 'Email sent.',
            'scheduled' => 'Email scheduled. The task will complete when delivery succeeds.',
            default => 'Email queued. The task will complete when delivery succeeds.',
        });
    }
}
