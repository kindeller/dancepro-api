<?php

namespace App\Features\Customers\Controllers;

use App\Features\Customers\Services\CustomerAccountEmailChange;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CustomerAccountEmailController extends Controller
{
    public function confirm(Request $request, CustomerAccountEmailChange $change): RedirectResponse
    {
        $change->confirm($request->user(), $request->string('account')->toString(), $request->string('hash')->toString(), $request->session()->getId());

        return to_route('customer.profile.edit')->with('status', 'Email address confirmed. Use your new address next time you sign in.');
    }
}
