<?php

namespace App\Features\Customers\Controllers;

use App\Features\Customers\Actions\SaveCustomerMediaProfile;
use App\Features\Customers\Requests\CustomerCompetitionsRequest;
use App\Features\Customers\Requests\CustomerProfilePageRequest;
use App\Features\Customers\Requests\SaveCustomerMediaProfileRequest;
use App\Features\Customers\Services\CustomerMediaProfile;
use App\Features\Studios\Models\Studio;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class CustomerProfileController extends Controller
{
    public function edit(CustomerProfilePageRequest $request, CustomerMediaProfile $profile): Response
    {
        return response()->view('customers.profile.edit', $profile->editor($request->user()) + [
            'profileTab' => $request->validated('tab') ?? 'details',
        ])->header('Cache-Control', 'private, no-store');
    }

    public function studio(CustomerCompetitionsRequest $request, Studio $studio, CustomerMediaProfile $profile): Response
    {
        return response()->view('customers.profile.studio', $profile->studioPhotos($request->user(), $studio))->header('Cache-Control', 'private, no-store');
    }

    public function store(SaveCustomerMediaProfileRequest $request, SaveCustomerMediaProfile $save): RedirectResponse
    {
        $save->execute($request->user(), $request->validated());

        $message = match ($request->validated('action')) {
            'details' => 'Your details have been saved.',
            'preferences' => 'Your email preferences have been saved.',
            'dancer' => 'Dancer saved. You can now add a competition in My Media.',
            'dancer_update' => 'Dancer details updated.',
            'dancer_remove' => 'Dancer removed from your profile. Your media access is unchanged.',
            'studio_remove' => 'Studio removed from your profile. Previously added media is unchanged.',
            default => 'Studio added. Return to My Media to access its photos and videos.',
        };

        $tab = match ($request->validated('action')) {
            'preferences' => 'preferences',
            'dancer', 'dancer_update', 'dancer_remove' => 'dancers',
            'studio', 'studio_remove' => 'studios',
            default => 'details',
        };

        return to_route('customer.profile.edit', ['tab' => $tab])->with('status', $message);
    }
}
