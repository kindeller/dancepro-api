<?php

namespace App\Features\Customers\Controllers;

use App\Features\Customers\Actions\RegisterCustomerCompetition;
use App\Features\Customers\Requests\CustomerCompetitionGalleryRequest;
use App\Features\Customers\Requests\CustomerCompetitionsRequest;
use App\Features\Customers\Requests\RegisterCustomerCompetitionRequest;
use App\Features\Customers\Services\CustomerCompetitionAccess;
use App\Features\Customers\Services\CustomerCompetitionCart;
use App\Features\Customers\Services\CustomerCompetitionTabs;
use App\Features\Customers\Services\CustomerDancerRoutines;
use App\Features\Customers\Services\CustomerEngagementTracker;
use App\Features\Customers\Services\CustomerImprovisationRoutines;
use App\Features\Customers\Services\CustomerStudioRoutines;
use App\Features\Programmes\Services\CompetitionCustomerPreview;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerCompetitionController extends Controller
{
    public function index(CustomerCompetitionsRequest $request, CustomerCompetitionAccess $access): Response
    {
        return response()->view('customers.competitions.index', $access->events($request->user()))->header('Cache-Control', 'private, no-store');
    }

    public function store(RegisterCustomerCompetitionRequest $request, RegisterCustomerCompetition $register): RedirectResponse
    {
        $event = $register->execute($request->user(), $request->validated('event_uuid'), $request->validated('login_code'));

        return to_route('customer.competitions.show', $event);
    }

    public function show(CustomerCompetitionGalleryRequest $request, SchedulingEvent $schedulingEvent, CompetitionCustomerPreview $gallery, CustomerCompetitionCart $cart, CustomerCompetitionTabs $tabs, CustomerEngagementTracker $engagement): Response
    {
        $engagement->record($request->user(), 'competition', $schedulingEvent->uuid, 'viewed', $request->validated('folder'));
        $galleryData = $gallery->browse($schedulingEvent, $request->validated('folder'), 'customer.competitions.image');
        $itemUuid = ($galleryData['selected']['kind'] ?? null) === 'item' ? $galleryData['selected']['uuid'] : null;

        return response()->view('customers.competitions.gallery', $galleryData + [
            'shopping' => $cart->forEvent($request->user(), $schedulingEvent),
            'videoAvailable' => $itemUuid ? $cart->videoAvailable($schedulingEvent, $itemUuid) : false,
            'packagePrice' => $itemUuid ? $cart->packagePrice($request->user(), $schedulingEvent, $itemUuid) : 4500,
            'focusPhoto' => $request->validated('photo'),
            'activeTab' => 'event'] + $tabs->forEvent($request->user(), $schedulingEvent))
            ->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function dancers(CustomerCompetitionGalleryRequest $request, SchedulingEvent $schedulingEvent, CustomerDancerRoutines $routines, CustomerCompetitionTabs $tabs): Response
    {
        return response()->view('customers.competitions.dancers', $routines->forEvent($request->user(), $schedulingEvent)
            + ['activeTab' => 'dancers'] + $tabs->forEvent($request->user(), $schedulingEvent))
            ->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function studios(CustomerCompetitionGalleryRequest $request, SchedulingEvent $schedulingEvent, CustomerStudioRoutines $routines, CustomerCompetitionTabs $tabs): Response
    {
        return response()->view('customers.competitions.studios', $routines->forEvent($request->user(), $schedulingEvent)
            + ['activeTab' => 'studios'] + $tabs->forEvent($request->user(), $schedulingEvent))
            ->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function improvisation(CustomerCompetitionGalleryRequest $request, SchedulingEvent $schedulingEvent, CustomerImprovisationRoutines $routines, CustomerCompetitionTabs $tabs): Response
    {
        return response()->view('customers.competitions.improvisation', $routines->forEvent($schedulingEvent)
            + ['activeTab' => 'improvisation'] + $tabs->forEvent($request->user(), $schedulingEvent))
            ->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function image(CustomerCompetitionGalleryRequest $request, SchedulingEvent $schedulingEvent, string $photo, CompetitionCustomerPreview $gallery): StreamedResponse
    {
        return $gallery->image($schedulingEvent, $photo, $request->validated('size', 'preview'));
    }
}
