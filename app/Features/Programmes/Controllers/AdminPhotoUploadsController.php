<?php

namespace App\Features\Programmes\Controllers;

use App\Features\Programmes\Actions\ResolvePhotoContributionException;
use App\Features\Programmes\Requests\AdminPhotoUploadsRequest;
use App\Features\Programmes\Requests\PhotoContributionExceptionRequest;
use App\Features\Programmes\Services\AdminPhotoUploads;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdminPhotoUploadsController extends Controller
{
    public function show(AdminPhotoUploadsRequest $request, SchedulingEvent $schedulingEvent, AdminPhotoUploads $uploads): View
    {
        return view('admin.programmes.photo-uploads', $uploads->forEvent($schedulingEvent, $request->validated('plan_uuid')));
    }

    public function update(PhotoContributionExceptionRequest $request, SchedulingEvent $schedulingEvent, ResolvePhotoContributionException $resolve): RedirectResponse
    {
        $count = $resolve->execute($schedulingEvent, $request->user(), $request->validated());

        return to_route('admin.photo-uploads.show', ['schedulingEvent' => $schedulingEvent, 'plan_uuid' => $request->validated('plan_uuid')])
            ->with('status', $count.' item contribution(s) updated. Photos remain private.');
    }
}
