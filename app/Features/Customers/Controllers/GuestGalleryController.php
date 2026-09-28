<?php

namespace App\Features\Customers\Controllers;

use App\Features\Customers\Services\GuestGalleryCatalogue;
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

        $type = $request->query('type') === 'concert' ? 'concert' : 'competition';

        return response()->view('customers.guest-galleries.index', [
            'type' => $type, 'cards' => $catalogue->cards($type), 'concert' => null, 'studio' => null,
        ])->header('Cache-Control', 'private, no-store');
    }
}
