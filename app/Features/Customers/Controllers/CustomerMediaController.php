<?php

namespace App\Features\Customers\Controllers;

use App\Features\Customers\Actions\AddCustomerConcertVideo;
use App\Features\Customers\Requests\AddConcertVideoRequest;
use App\Features\Customers\Requests\CustomerCompetitionsRequest;
use App\Features\Customers\Requests\CustomerMediaIndexRequest;
use App\Features\Customers\Services\CustomerAccountMediaSelections;
use App\Features\Customers\Services\CustomerMedia;
use App\Features\Customers\Services\CustomerRecommendedMedia;
use App\Features\Payments\Services\CustomerCredits;
use App\Features\Programmes\Services\CompetitionCustomerPreview;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerMediaController extends Controller
{
    public function index(CustomerMediaIndexRequest $request, CustomerMedia $media, CustomerAccountMediaSelections $selections): Response
    {
        $notifications = $request->user()->notifications()->latest()->limit(10)->get();
        $request->user()->unreadNotifications->markAsRead();
        $selectionCounts = $selections->counts($request->user());

        $mediaTab = $request->validated('tab') ?? 'events';

        return response()->view('customers.media.index', $media->forUser($request->user()) + compact('notifications', 'selectionCounts', 'mediaTab'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function cart(CustomerCompetitionsRequest $request, CustomerAccountMediaSelections $selections, CustomerCredits $credits): Response
    {
        return response()->view('customers.media.selections', $selections->forUser(
            $request->user(), 'cart', $request->validated('focus_kind'), $request->validated('focus_event')
        ) + ['creditBalance' => $credits->balance($request->user())])
            ->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function favourites(CustomerCompetitionsRequest $request, CustomerAccountMediaSelections $selections): Response
    {
        return response()->view('customers.media.selections', $selections->forUser(
            $request->user(), 'favourites', $request->validated('focus_kind'), $request->validated('focus_event')
        ))
            ->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function addConcert(AddConcertVideoRequest $request, AddCustomerConcertVideo $add): RedirectResponse
    {
        $count = $add->execute($request->user(), $request->validated('concert_code'), (int) $request->validated('year'));

        return to_route('customer.media.index')->with('status', $count === 1 ? 'Concert video added to My Media.' : "$count concerts added to My Media.");
    }

    public function recommendationThumbnail(CustomerCompetitionsRequest $request, SchedulingEvent $schedulingEvent, string $item, string $photo, CustomerRecommendedMedia $recommendations, CompetitionCustomerPreview $preview): StreamedResponse
    {
        abort_unless($recommendations->allowsThumbnail($request->user(), $schedulingEvent, $item, $photo), 404);

        return $preview->image($schedulingEvent, $photo, 'thumbnail');
    }
}
