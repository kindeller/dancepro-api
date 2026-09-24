<?php

namespace App\Features\Customers\Controllers;

use App\Features\Customers\Actions\CreateCustomerAccount;
use App\Features\Customers\Requests\RegisterCustomerRequest;
use App\Features\Customers\Services\TransferGuestGalleryActivity;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CustomerAccountController extends Controller
{
    public function login(): View
    {
        return view('customers.account.login');
    }

    public function create(Request $request): View
    {
        return view('customers.account.register', ['invitedEmail' => $request->string('email')->toString()]);
    }

    public function store(RegisterCustomerRequest $request, CreateCustomerAccount $create, TransferGuestGalleryActivity $guestActivity): RedirectResponse
    {
        $user = $create->execute($request->validated());
        Auth::login($user);
        $request->session()->regenerate();
        $guestActivity->execute($request, $user);
        $user->sendEmailVerificationNotification();

        return to_route('customer.verification.notice');
    }
}
