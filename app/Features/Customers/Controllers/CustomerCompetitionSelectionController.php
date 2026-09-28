<?php

namespace App\Features\Customers\Controllers;

use App\Features\Customers\Requests\CustomerCompetitionGalleryRequest;
use App\Features\Customers\Requests\SaveCustomerMediaSelectionRequest;
use App\Features\Customers\Requests\SaveCustomerPhotoBatchRequest;
use App\Features\Customers\Services\CustomerCompetitionAccess;
use App\Features\Customers\Services\CustomerCompetitionCart;
use App\Features\Customers\Services\CustomerEngagementTracker;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class CustomerCompetitionSelectionController extends Controller
{
    public function store(SaveCustomerMediaSelectionRequest $request, SchedulingEvent $schedulingEvent, CustomerCompetitionCart $cart, CustomerEngagementTracker $engagement): RedirectResponse|JsonResponse
    {
        $input = $request->validated();
        $state = $cart->save($request->user(), $schedulingEvent, $input);
        $engagement->record($request->user(), 'competition', $schedulingEvent->uuid, $input['action'], $input['item_uuid'], ['product' => $input['product']]);
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

    public function storePhotos(SaveCustomerPhotoBatchRequest $request, SchedulingEvent $schedulingEvent, CustomerCompetitionCart $cart, CustomerEngagementTracker $engagement): RedirectResponse
    {
        $data = $request->validated();
        $cart->savePhotos($request->user(), $schedulingEvent, $data['item_uuid'], $data['photo_uuids'], $data['action']);
        $engagement->record($request->user(), 'competition', $schedulingEvent->uuid, $data['action'], $data['item_uuid'], ['photo_count' => count($data['photo_uuids'])]);

        return back()->with('status', count($data['photo_uuids']).($data['action'] === 'add'
            ? ' photos added to your cart.' : ' photos saved to favourites.'));
    }

    public function cart(CustomerCompetitionGalleryRequest $request, SchedulingEvent $schedulingEvent, CustomerCompetitionAccess $access): RedirectResponse
    {
        abort_unless($access->allows($request->user(), $schedulingEvent), 403);

        return to_route('customer.media.cart', ['focus_kind' => 'competition', 'focus_event' => $schedulingEvent->uuid]);
    }

    public function favourites(CustomerCompetitionGalleryRequest $request, SchedulingEvent $schedulingEvent, CustomerCompetitionAccess $access): RedirectResponse
    {
        abort_unless($access->allows($request->user(), $schedulingEvent), 403);

        return to_route('customer.media.favourites', ['focus_kind' => 'competition', 'focus_event' => $schedulingEvent->uuid]);
    }
}
