<?php

namespace App\Features\Customers\Controllers;

use App\Features\Customers\Requests\CustomerCompetitionGalleryRequest;
use App\Features\Customers\Requests\SaveCustomerMediaSelectionRequest;
use App\Features\Customers\Requests\SaveCustomerPhotoBatchRequest;
use App\Features\Customers\Services\CustomerCompetitionAccess;
use App\Features\Customers\Services\CustomerCompetitionCart;
use App\Features\Customers\Services\CustomerCompetitionTabs;
use App\Features\Payments\Services\CustomerCredits;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class CustomerCompetitionSelectionController extends Controller
{
    public function store(SaveCustomerMediaSelectionRequest $request, SchedulingEvent $schedulingEvent, CustomerCompetitionCart $cart): RedirectResponse|JsonResponse
    {
        $input = $request->validated();
        $state = $cart->save($request->user(), $schedulingEvent, $input);
        $message = match ($input['action']) {
            'add' => 'Added to your cart.', 'remove' => 'Removed from your cart.',
            'favourite' => 'Saved to favourites.', default => 'Removed from favourites.',
        };

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $message, 'data' => $state + [
                'product' => $input['product'], 'item_uuid' => $input['item_uuid'], 'photo_uuid' => $input['photo_uuid'] ?? null,
            ]])->header('Cache-Control', 'private, no-store');
        }

        return back()->with('status', $message);
    }

    public function storePhotos(SaveCustomerPhotoBatchRequest $request, SchedulingEvent $schedulingEvent, CustomerCompetitionCart $cart): RedirectResponse
    {
        $data = $request->validated();
        $cart->savePhotos($request->user(), $schedulingEvent, $data['item_uuid'], $data['photo_uuids'], $data['action']);

        return back()->with('status', count($data['photo_uuids']).($data['action'] === 'add'
            ? ' photos added to your cart.' : ' photos saved to favourites.'));
    }

    public function cart(CustomerCompetitionGalleryRequest $request, SchedulingEvent $schedulingEvent, CustomerCompetitionCart $cart, CustomerCompetitionAccess $access, CustomerCompetitionTabs $tabs, CustomerCredits $credits): Response
    {
        abort_unless($access->allows($request->user(), $schedulingEvent), 403);

        return response()->view('customers.competitions.cart', ['event' => $schedulingEvent, 'activeTab' => 'cart']
            + $cart->forEvent($request->user(), $schedulingEvent, includeCards: true)
            + ['creditBalance' => $credits->balance($request->user())]
            + $tabs->forEvent($request->user(), $schedulingEvent))
            ->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function favourites(CustomerCompetitionGalleryRequest $request, SchedulingEvent $schedulingEvent, CustomerCompetitionCart $cart, CustomerCompetitionAccess $access, CustomerCompetitionTabs $tabs, CustomerCredits $credits): Response
    {
        abort_unless($access->allows($request->user(), $schedulingEvent), 403);

        return response()->view('customers.competitions.cart', ['event' => $schedulingEvent, 'activeTab' => 'favourites']
            + $cart->forEvent($request->user(), $schedulingEvent, includeCards: true)
            + ['creditBalance' => $credits->balance($request->user())]
            + $tabs->forEvent($request->user(), $schedulingEvent))
            ->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
