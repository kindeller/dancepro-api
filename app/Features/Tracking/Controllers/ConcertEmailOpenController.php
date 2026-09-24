<?php

namespace App\Features\Tracking\Controllers;

use App\Features\Tracking\Models\ConcertTrackerEmailDelivery;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class ConcertEmailOpenController extends Controller
{
    public function __invoke(ConcertTrackerEmailDelivery $delivery): Response
    {
        $delivery->increment('open_count');
        $delivery->update([
            'first_opened_at' => $delivery->first_opened_at ?? now(),
            'last_opened_at' => now(),
        ]);

        return response(base64_decode('R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs='), 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
