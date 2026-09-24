<?php

namespace App\Features\Customers\Controllers;

use App\Features\Customers\Requests\CustomerCompetitionsRequest;
use App\Features\Customers\Services\CustomerOrderHistory;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class CustomerOrderController extends Controller
{
    public function index(CustomerCompetitionsRequest $request, CustomerOrderHistory $orders): Response
    {
        return response()->view('customers.media.orders', ['orders' => $orders->forUser($request->user())])
            ->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
