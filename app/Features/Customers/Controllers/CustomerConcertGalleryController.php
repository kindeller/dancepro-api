<?php

namespace App\Features\Customers\Controllers;

use App\Features\Concerts\Models\Concert;
use App\Features\Customers\Requests\CustomerConcertGalleryRequest;
use App\Features\Customers\Services\ConcertPhotoPreview;
use App\Features\Customers\Services\CustomerConcertGallery;
use App\Features\Customers\Services\CustomerConcertPhotoCart;
use App\Features\Customers\Services\CustomerEngagementTracker;
use App\Features\Customers\Services\StudioPrincipalPhotoPricing;
use App\Features\Media\Services\MediaPriceLists;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerConcertGalleryController extends Controller
{
    public function show(CustomerConcertGalleryRequest $request, Concert $concert, CustomerConcertGallery $gallery, CustomerConcertPhotoCart $cart, MediaPriceLists $priceLists, StudioPrincipalPhotoPricing $principalPricing, CustomerEngagementTracker $engagement): Response
    {
        $engagement->record($request->user(), 'concert', $concert->uuid, 'viewed', $request->validated('folder'));
        $galleryData = $gallery->browse($request->user(), $concert, $request->validated('folder'), $request->validated('group'));
        $shopping = $cart->forStudio($request->user(), $concert->studio);
        $shopping['photoPrice'] = $principalPricing->eligible($request->user(), $concert->studio)
            ? StudioPrincipalPhotoPricing::PRICE
            : $priceLists->concertPrice($concert, $galleryData['selected']);

        return response()->view('customers.concerts.gallery', $galleryData + ['shopping' => $shopping])
            ->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function image(CustomerConcertGalleryRequest $request, Concert $concert, string $asset, CustomerConcertGallery $gallery, ConcertPhotoPreview $preview): StreamedResponse
    {
        return $gallery->image($request->user(), $concert, $asset, $request->validated('size', 'preview'), $preview);
    }
}
