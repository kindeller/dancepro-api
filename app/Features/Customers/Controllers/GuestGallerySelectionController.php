<?php

namespace App\Features\Customers\Controllers;

use App\Features\Concerts\Models\Concert;
use App\Features\Customers\Requests\GuestCompetitionGalleryRequest;
use App\Features\Customers\Requests\GuestGallerySelectionRequest;
use App\Features\Customers\Requests\GuestPhotoBatchRequest;
use App\Features\Customers\Services\GuestCompetitionCart;
use App\Features\Customers\Services\GuestGalleryAccessManager;
use App\Features\Customers\Services\GuestGallerySelections;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class GuestGallerySelectionController extends Controller
{
    public function competition(GuestGallerySelectionRequest $request, SchedulingEvent $schedulingEvent, GuestGalleryAccessManager $access, GuestCompetitionCart $cart): RedirectResponse|JsonResponse
    {
        $input = $request->validated();
        $state = $cart->save($access->competition($request, $schedulingEvent), $schedulingEvent, $input);
        $message = match ($input['action']) {
            'add' => 'Added to your cart.', 'remove' => 'Removed from your cart.',
            'favourite' => 'Saved to favourites.', default => 'Removed from favourites.',
        };
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $message, 'data' => $state + [
                'product' => $input['product'] ?? 'photo', 'item_uuid' => $input['item_uuid'],
                'photo_uuid' => $input['photo_uuid'] ?? null,
            ]])->header('Cache-Control', 'private, no-store');
        }

        return back()->with('status', $message);
    }

    public function competitionPhotos(GuestPhotoBatchRequest $request, SchedulingEvent $schedulingEvent, GuestGalleryAccessManager $access, GuestCompetitionCart $cart): RedirectResponse
    {
        $input = $request->validated();
        $cart->savePhotos($access->competition($request, $schedulingEvent), $schedulingEvent, $input['item_uuid'], $input['photo_uuids'], $input['action']);

        return back()->with('status', count($input['photo_uuids']).($input['action'] === 'add'
            ? ' photos added to your cart.' : ' photos saved to favourites.'));
    }

    public function competitionCart(GuestCompetitionGalleryRequest $request, SchedulingEvent $schedulingEvent, GuestGalleryAccessManager $access, GuestCompetitionCart $cart): Response
    {
        return $this->competitionList($request, $schedulingEvent, $access, $cart, 'cart');
    }

    public function competitionFavourites(GuestCompetitionGalleryRequest $request, SchedulingEvent $schedulingEvent, GuestGalleryAccessManager $access, GuestCompetitionCart $cart): Response
    {
        return $this->competitionList($request, $schedulingEvent, $access, $cart, 'favourites');
    }

    public function concert(GuestGallerySelectionRequest $request, Concert $concert, GuestGalleryAccessManager $access, GuestGallerySelections $selections): RedirectResponse
    {
        $selections->concert($access->concert($request, $concert), $concert, $request->validated());

        return back()->with('status', 'Guest favourites and cart updated.');
    }

    private function competitionList(GuestCompetitionGalleryRequest $request, SchedulingEvent $event, GuestGalleryAccessManager $manager, GuestCompetitionCart $cart, string $tab): Response
    {
        $access = $manager->competition($request, $event);
        $counts = $cart->counts($access);
        $routes = ['show' => 'guest-galleries.competitions.show', 'image' => 'guest-galleries.competitions.image',
            'selection' => 'guest-galleries.competitions.selections', 'checkout' => 'guest-galleries.competitions.checkout'];
        $tabRoutes = ['event' => 'guest-galleries.competitions.show', 'dancers' => 'guest-galleries.competitions.dancers',
            'studios' => 'guest-galleries.competitions.studios', 'improvisation' => 'guest-galleries.competitions.improvisation',
            'favourites' => 'guest-galleries.competitions.favourites', 'cart' => 'guest-galleries.competitions.cart'];

        return response()->view('customers.competitions.cart', ['event' => $event, 'activeTab' => $tab,
            'studioLogoUrl' => null, 'cartCount' => $counts['cart_count'], 'favouriteCount' => $counts['favourite_count'],
            'competitionRoutes' => $routes, 'competitionTabRoutes' => $tabRoutes, 'guestCheckout' => true]
            + $cart->forEvent($access, $event, includeCards: true))->header('Cache-Control', 'private, no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
