<?php

namespace App\Features\Customers\Controllers;

use App\Features\Customers\Models\GuestGalleryAccess;
use App\Features\Customers\Requests\GuestCompetitionGalleryRequest;
use App\Features\Customers\Services\CustomerImprovisationRoutines;
use App\Features\Customers\Services\GuestCompetitionCart;
use App\Features\Customers\Services\GuestGalleryAccessManager;
use App\Features\Media\Services\MediaPriceLists;
use App\Features\Programmes\Services\CompetitionCustomerPreview;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GuestCompetitionGalleryController extends Controller
{
    public function show(GuestCompetitionGalleryRequest $request, SchedulingEvent $schedulingEvent, CompetitionCustomerPreview $gallery, GuestGalleryAccessManager $access, GuestCompetitionCart $cart, MediaPriceLists $priceLists): Response
    {
        $data = $gallery->browse($schedulingEvent, $request->validated('folder'), 'guest-galleries.competitions.image');
        $guestAccess = $access->competition($request, $schedulingEvent);
        $itemUuid = ($data['selected']['kind'] ?? null) === 'item' ? $data['selected']['uuid'] : null;

        return response()->view('customers.competitions.gallery', $data + [
            'shopping' => $cart->forEvent($guestAccess, $schedulingEvent),
            'videoAvailable' => $itemUuid ? $cart->videoAvailable($schedulingEvent, $itemUuid) : false,
            'packagePrice' => $priceLists->competitionPrice($schedulingEvent, 'competition_routine_package', $itemUuid),
            'focusPhoto' => $request->validated('photo'), 'activeTab' => 'event',
        ] + $this->guestViewData($guestAccess))
            ->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function dancers(GuestCompetitionGalleryRequest $request, SchedulingEvent $schedulingEvent, GuestGalleryAccessManager $access): Response
    {
        return $this->profilePage($access->competition($request, $schedulingEvent), $schedulingEvent, 'dancers');
    }

    public function studios(GuestCompetitionGalleryRequest $request, SchedulingEvent $schedulingEvent, GuestGalleryAccessManager $access): Response
    {
        return $this->profilePage($access->competition($request, $schedulingEvent), $schedulingEvent, 'studios');
    }

    public function improvisation(GuestCompetitionGalleryRequest $request, SchedulingEvent $schedulingEvent, GuestGalleryAccessManager $access, CustomerImprovisationRoutines $routines): Response
    {
        $guestAccess = $access->competition($request, $schedulingEvent);

        return response()->view('customers.competitions.improvisation', $routines->forEvent($schedulingEvent)
            + ['activeTab' => 'improvisation'] + $this->guestViewData($guestAccess))
            ->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function checkout(GuestCompetitionGalleryRequest $request, SchedulingEvent $schedulingEvent, GuestGalleryAccessManager $access, GuestCompetitionCart $cart): RedirectResponse
    {
        $guestAccess = $access->competition($request, $schedulingEvent);
        abort_unless($cart->counts($guestAccess)['cart_count'] > 0, 404);
        $request->session()->put('checkout.discount_code', $request->validated('discount_code'));
        $request->session()->put('url.intended', route('customer.competitions.cart', $schedulingEvent));

        return to_route($request->query('account') === 'login' ? 'customer.login' : 'customer.register');
    }

    public function image(GuestCompetitionGalleryRequest $request, SchedulingEvent $schedulingEvent, string $photo, CompetitionCustomerPreview $gallery): StreamedResponse
    {
        return $gallery->image($schedulingEvent, $photo, $request->validated('size', 'preview'));
    }

    private function profilePage(GuestGalleryAccess $access, SchedulingEvent $event, string $tab): Response
    {
        return response()->view('customers.competitions.'.$tab, [
            'event' => $event, 'title' => $event->name, 'routines' => [], 'activeTab' => $tab,
            'dancers' => collect(), 'studios' => collect(), 'guestProfileRequired' => true,
        ] + $this->guestViewData($access))->header('Cache-Control', 'private, no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    private function guestViewData(GuestGalleryAccess $access): array
    {
        $counts = app(GuestCompetitionCart::class)->counts($access);

        return [
            'studioLogoUrl' => null, 'cartCount' => $counts['cart_count'], 'favouriteCount' => $counts['favourite_count'],
            'competitionHomeUrl' => route('guest-galleries.index'),
            'competitionRoutes' => ['show' => 'guest-galleries.competitions.show', 'image' => 'guest-galleries.competitions.image',
                'selection' => 'guest-galleries.competitions.selections', 'batch' => 'guest-galleries.competitions.selections.photos'],
            'competitionTabRoutes' => ['event' => 'guest-galleries.competitions.show', 'dancers' => 'guest-galleries.competitions.dancers',
                'studios' => 'guest-galleries.competitions.studios', 'improvisation' => 'guest-galleries.competitions.improvisation',
                'favourites' => 'guest-galleries.competitions.favourites', 'cart' => 'guest-galleries.competitions.cart'],
        ];
    }
}
