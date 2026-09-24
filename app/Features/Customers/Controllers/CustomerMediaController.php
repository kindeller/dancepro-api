<?php

namespace App\Features\Customers\Controllers;

use App\Features\Customers\Actions\AddCustomerConcertVideo;
use App\Features\Customers\Requests\AddConcertVideoRequest;
use App\Features\Customers\Requests\CustomerCompetitionsRequest;
use App\Features\Customers\Services\CustomerMedia;
use App\Features\Customers\Services\CustomerRecommendedMedia;
use App\Features\Programmes\Services\CompetitionCustomerPreview;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerMediaController extends Controller
{
    public function index(CustomerCompetitionsRequest $request, CustomerMedia $media): Response
    {
        return response()->view('customers.media.index', $media->forUser($request->user()))->header('Cache-Control', 'private, no-store');
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
