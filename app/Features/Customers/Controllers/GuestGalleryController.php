<?php

namespace App\Features\Customers\Controllers;

use App\Features\Customers\Services\GuestGalleryAccessManager;
use App\Features\Customers\Services\GuestGalleryCatalogue;
use App\Features\Studios\Models\Studio;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GuestGalleryController extends Controller
{
    public function index(Request $request, GuestGalleryCatalogue $catalogue): Response|RedirectResponse
    {
        if ($request->user()?->canAccessCustomer()) {
            return to_route('customer.media.index');
        }

        $galleryTypes = $catalogue->availableTypes();
        $requestedType = $request->query('type');
        $type = is_string($requestedType) && isset($galleryTypes[$requestedType]) ? $requestedType : 'competition';

        return response()->view('customers.guest-galleries.index', [
            'type' => $type, 'galleryTypes' => $galleryTypes,
            'cards' => $type === 'concert' ? $catalogue->studios() : $catalogue->cards($type), 'concert' => null, 'studio' => null,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function studio(Request $request, Studio $studio, GuestGalleryCatalogue $catalogue, GuestGalleryAccessManager $access): Response|RedirectResponse
    {
        $cards = $catalogue->concerts($studio);
        abort_if($cards->isEmpty(), 404);
        if ($cards->contains('released', true) && ! $access->studioAccess($request, $studio)) {
            return to_route('guest-galleries.studios.access', $studio);
        }

        return response()->view('customers.guest-galleries.index', [
            'type' => 'concert', 'cards' => $cards, 'galleryStudio' => $studio, 'studio' => $studio, 'concert' => null,
        ])->header('Cache-Control', 'private, no-store');
    }
}
