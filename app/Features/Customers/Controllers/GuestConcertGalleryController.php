<?php

namespace App\Features\Customers\Controllers;

use App\Features\Concerts\Models\Concert;
use App\Features\Customers\Requests\GuestConcertGalleryRequest;
use App\Features\Customers\Services\ConcertPhotoPreview;
use App\Features\Customers\Services\CustomerConcertGallery;
use App\Features\Customers\Services\GuestGalleryAccessManager;
use App\Features\Customers\Services\GuestGallerySelections;
use App\Features\Media\Models\ConcertPhotoReleaseGroup;
use App\Features\Media\Services\MediaPriceLists;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GuestConcertGalleryController extends Controller
{
    public function cover(Concert $concert, ConcertPhotoReleaseGroup $group, CustomerConcertGallery $gallery, ConcertPhotoPreview $preview): StreamedResponse
    {
        return $gallery->publicCover($concert, $group, $preview);
    }

    public function show(GuestConcertGalleryRequest $request, Concert $concert, CustomerConcertGallery $gallery, GuestGalleryAccessManager $access, GuestGallerySelections $selections, MediaPriceLists $priceLists): Response
    {
        $guestAccess = $access->concert($request, $concert);
        $galleryData = $gallery->browseReleased($concert, $request->validated('folder'), $request->validated('group'));
        $shopping = $selections->forStudio($guestAccess, $concert->studio);
        $shopping['photoPrice'] = $priceLists->concertPrice($concert, $galleryData['selected']);

        return response()->view('customers.concerts.gallery', $galleryData
            + ['shopping' => $shopping,
                'concertRoutes' => $this->routes(), 'concertHomeUrl' => route('guest-galleries.index'),
                'concertCartTarget' => $concert])
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function cart(GuestConcertGalleryRequest $request, Concert $concert, GuestGalleryAccessManager $access, GuestGallerySelections $selections): Response
    {
        $guestAccess = $access->concert($request, $concert);

        return response()->view('customers.concerts.cart', $selections->forStudio($guestAccess, $concert->studio) + [
            'concertRoutes' => $this->routes(), 'concertBackUrl' => route('guest-galleries.concerts.show', $concert),
            'guestCheckout' => true, 'activeTab' => $request->validated('tab') ?? 'cart',
        ])->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function checkout(GuestConcertGalleryRequest $request, Concert $concert, GuestGalleryAccessManager $access, GuestGallerySelections $selections): RedirectResponse
    {
        $guestAccess = $access->concert($request, $concert);
        abort_unless($selections->forStudio($guestAccess, $concert->studio)['cart']->isNotEmpty(), 404);
        $request->session()->put('checkout.discount_code', $request->validated('discount_code'));
        $request->session()->put('url.intended', route('customer.concerts.photos.cart', $concert->studio));

        return to_route($request->query('account') === 'login' ? 'customer.login' : 'customer.register');
    }

    public function image(GuestConcertGalleryRequest $request, Concert $concert, string $asset, CustomerConcertGallery $gallery, ConcertPhotoPreview $preview): StreamedResponse
    {
        return $gallery->imageReleased($concert, $asset, $request->validated('size', 'preview'), $preview);
    }

    private function routes(): array
    {
        return ['show' => 'guest-galleries.concerts.show', 'image' => 'guest-galleries.concerts.image',
            'selection' => 'guest-galleries.concerts.selections', 'cart' => 'guest-galleries.concerts.cart',
            'checkout' => 'guest-galleries.concerts.checkout'];
    }
}
