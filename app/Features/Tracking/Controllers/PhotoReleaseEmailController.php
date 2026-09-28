<?php

namespace App\Features\Tracking\Controllers;

use App\Features\Media\Models\ConcertPhotoReleaseGroup;
use App\Features\Tracking\Requests\ConcertTrackerRequest;
use App\Features\Tracking\Requests\SendPhotoReleaseEmailRequest;
use App\Features\Tracking\Services\BookingTrackerBar;
use App\Features\Tracking\Services\EmailBranding;
use App\Features\Tracking\Services\PhotoReleaseEmails;
use App\Http\Controllers\Controller;

class PhotoReleaseEmailController extends Controller
{
    public function show(ConcertTrackerRequest $request, ConcertPhotoReleaseGroup $group, PhotoReleaseEmails $emails, BookingTrackerBar $bar, EmailBranding $branding)
    {
        $data = $emails->page($group);

        return response()->view('admin.tracking.photo-release-email', $data + [
            'trackerBar' => $bar->releasePage($data, $request->session()->get('concert_tracker_context', [])),
            'emailBranding' => $branding->for($request->user()->id),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function send(SendPhotoReleaseEmailRequest $request, ConcertPhotoReleaseGroup $group, PhotoReleaseEmails $emails)
    {
        $count = $emails->send($group, $request->user());

        return back()->with('status', $count.' release emails queued. Previously sent or pending recipients were skipped.');
    }
}
