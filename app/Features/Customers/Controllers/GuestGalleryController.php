<?php

namespace App\Features\Customers\Controllers;

use App\Features\Customers\Services\GuestGalleryCatalogue;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GuestGalleryController extends Controller
{
    public function index(Request $request, GuestGalleryCatalogue $catalogue): Response
    {
        $type = $request->query('type') === 'concert' ? 'concert' : 'competition';

        return response()->view('customers.guest-galleries.index', [
            'type' => $type, 'cards' => $catalogue->cards($type), 'concert' => null, 'studio' => null,
        ])->header('Cache-Control', 'private, no-store');
    }
}
