<?php

namespace App\Features\Customers\Controllers;

use App\Features\Concerts\Models\Concert;
use App\Features\Customers\Requests\CustomerCompetitionsRequest;
use App\Features\Customers\Requests\SaveCustomerConcertPhotoSelectionRequest;
use App\Features\Customers\Services\CustomerConcertPhotoCart;
use App\Features\Payments\Services\CustomerCredits;
use App\Features\Studios\Models\Studio;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class CustomerConcertPhotoSelectionController extends Controller
{
    public function store(SaveCustomerConcertPhotoSelectionRequest $request, Concert $concert, CustomerConcertPhotoCart $cart): RedirectResponse
    {
        $cart->save($request->user(), $concert, $request->validated('asset_uuid'), $request->validated('action'));

        return back()->with('status', match ($request->validated('action')) {
            'add' => 'Photo added to your cart.', 'remove' => 'Photo removed from your cart.',
            'favourite' => 'Photo saved to favourites.', default => 'Photo removed from favourites.',
        });
    }

    public function cart(CustomerCompetitionsRequest $request, Studio $studio, CustomerConcertPhotoCart $cart, CustomerCredits $credits): Response
    {
        return response()->view('customers.concerts.cart', $cart->forStudio($request->user(), $studio) + [
            'creditBalance' => $credits->balance($request->user()),
        ])
            ->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
