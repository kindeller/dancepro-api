<?php

namespace App\Features\Customers\Controllers;

use App\Features\Customers\Models\GuestStudioSubscription;
use App\Features\Customers\Requests\GuestStudioSubscriptionRequest;
use App\Features\Customers\Services\GuestStudioSubscriptions;
use App\Features\Studios\Models\Studio;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class GuestStudioSubscriptionController extends Controller
{
    public function store(GuestStudioSubscriptionRequest $request, Studio $studio, GuestStudioSubscriptions $subscriptions): RedirectResponse
    {
        $subscriptions->register($studio, $request->user()?->email ?? $request->validated('email'), $request->user());

        return back()->with('status', 'You’re registered. We’ll notify you when each new studio event gallery is released. You can unsubscribe at any time.');
    }

    public function unsubscribe(GuestStudioSubscription $subscription, GuestStudioSubscriptions $subscriptions): Response
    {
        $subscriptions->unsubscribe($subscription);

        return response()->view('customers.guest-galleries.unsubscribed', ['studioSubscription' => true, 'studio' => null, 'concert' => null]);
    }
}
