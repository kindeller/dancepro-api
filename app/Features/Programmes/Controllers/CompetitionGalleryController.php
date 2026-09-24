<?php

namespace App\Features\Programmes\Controllers;

use App\Features\Programmes\Requests\CompetitionGalleryRequest;
use App\Features\Programmes\Requests\CompetitionPhotosRequest;
use App\Features\Programmes\Requests\SaveCompetitionGallerySettingsRequest;
use App\Features\Programmes\Requests\SaveCompetitionRoutineStudioRequest;
use App\Features\Programmes\Services\CompetitionGallery;
use App\Features\Programmes\Services\CompetitionGallerySettings;
use App\Features\Programmes\Services\CompetitionRoutineStudios;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompetitionGalleryController extends Controller
{
    public function index(CompetitionPhotosRequest $request, CompetitionGallery $gallery): Response
    {
        return response()->view('admin.programmes.competition-photos', $gallery->events())
            ->header('Cache-Control', 'private, no-store');
    }

    public function show(CompetitionGalleryRequest $request, SchedulingEvent $schedulingEvent, CompetitionGallery $gallery, CompetitionRoutineStudios $routineStudios): Response
    {
        $browse = $gallery->browse($schedulingEvent, $request->validated('folder'));

        return response()->view('admin.programmes.competition-gallery', array_merge($browse, $routineStudios->editor($schedulingEvent, $browse)))
            ->header('Cache-Control', 'private, no-store');
    }

    public function settings(CompetitionGalleryRequest $request, SchedulingEvent $schedulingEvent, CompetitionGallerySettings $settings): Response
    {
        return response()->view('admin.programmes.competition-gallery-settings', $settings->editor($schedulingEvent))
            ->header('Cache-Control', 'private, no-store');
    }

    public function saveSettings(SaveCompetitionGallerySettingsRequest $request, SchedulingEvent $schedulingEvent, CompetitionGallerySettings $settings): RedirectResponse
    {
        $settings->save($schedulingEvent, $request->user(), $request->validated());

        return to_route('admin.competition-gallery.settings', $schedulingEvent)->with('status', 'Gallery settings saved.');
    }

    public function saveRoutineStudio(SaveCompetitionRoutineStudioRequest $request, SchedulingEvent $schedulingEvent, CompetitionRoutineStudios $studios): RedirectResponse
    {
        $studios->save($schedulingEvent, $request->user(), $request->validated());

        return to_route('admin.competition-gallery.show', ['schedulingEvent' => $schedulingEvent, 'folder' => $request->validated('item_uuid')])
            ->with('status', 'Routine studio updated.');
    }

    public function image(CompetitionGalleryRequest $request, SchedulingEvent $schedulingEvent, string $photo, CompetitionGallery $gallery): StreamedResponse
    {
        return $gallery->image($schedulingEvent, $photo, $request->validated('size', 'preview'));
    }
}
