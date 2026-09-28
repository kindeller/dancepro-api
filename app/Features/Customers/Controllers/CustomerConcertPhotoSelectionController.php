<?php

namespace App\Features\Customers\Controllers;

use App\Features\Concerts\Models\Concert;
use App\Features\Customers\Requests\CustomerConcertCartRequest;
use App\Features\Customers\Requests\SaveCustomerConcertPhotoSelectionRequest;
use App\Features\Customers\Services\CustomerConcertPhotoCart;
use App\Features\Customers\Services\CustomerEngagementTracker;
use App\Features\Studios\Models\Studio;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class CustomerConcertPhotoSelectionController extends Controller
{
    public function store(SaveCustomerConcertPhotoSelectionRequest $request, Concert $concert, CustomerConcertPhotoCart $cart, CustomerEngagementTracker $engagement): RedirectResponse|JsonResponse
    {
        $assetUuid = $request->validated('asset_uuid');
        $action = $request->validated('action');
        $cart->save($request->user(), $concert, $assetUuid, $action);
        $engagement->record($request->user(), 'concert', $concert->uuid, $action, $assetUuid);
        $message = match ($action) {
            'add' => 'Photo added to your cart.', 'remove' => 'Photo removed from your cart.',
            'favourite' => 'Photo saved to favourites.', default => 'Photo removed from favourites.',
        };
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $message, 'data' => $cart
                ->state($request->user(), $concert->studio, $assetUuid) + ['asset_uuid' => $assetUuid]])
                ->header('Cache-Control', 'private, no-store');
        }

        return back()->with('status', $message);
    }

    public function cart(CustomerConcertCartRequest $request, Studio $studio, CustomerConcertPhotoCart $cart): RedirectResponse
    {
        $cart->forStudio($request->user(), $studio);

        return to_route(($request->validated('tab') ?? 'cart') === 'favourites'
            ? 'customer.media.favourites' : 'customer.media.cart');
    }
}
