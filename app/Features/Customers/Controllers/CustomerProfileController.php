<?php

namespace App\Features\Customers\Controllers;

use App\Features\Customers\Actions\SaveCustomerMediaProfile;
use App\Features\Customers\Requests\CustomerCompetitionsRequest;
use App\Features\Customers\Requests\CustomerProfilePageRequest;
use App\Features\Customers\Requests\SaveCustomerMediaProfileRequest;
use App\Features\Customers\Requests\SearchProfileStudiosRequest;
use App\Features\Customers\Services\CustomerMediaProfile;
use App\Features\Families\Services\CustomerFamilies;
use App\Features\Studios\Models\Studio;
use App\Features\Studios\Services\StudioMemberships;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class CustomerProfileController extends Controller
{
    public function edit(CustomerProfilePageRequest $request, CustomerMediaProfile $profile): Response
    {
        return response()->view('customers.profile.edit', $profile->editor($request->user()) + [
            'profileTab' => $request->validated('tab') === 'dancers' ? 'family' : ($request->validated('tab') ?? 'details'),
            ...(in_array($request->validated('tab'), ['family', 'dancers'], true) ? app(CustomerFamilies::class)->page($request->user()) : []),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function studio(CustomerCompetitionsRequest $request, Studio $studio, CustomerMediaProfile $profile): Response
    {
        return response()->view('customers.profile.studio', $profile->studioPhotos($request->user(), $studio))->header('Cache-Control', 'private, no-store');
    }

    public function searchStudios(SearchProfileStudiosRequest $request, StudioMemberships $memberships): JsonResponse
    {
        return response()->json(['success' => true, 'message' => '', 'data' => $memberships->search($request->validated('q') ?? '')])->header('Cache-Control', 'private, no-store');
    }

    public function store(SaveCustomerMediaProfileRequest $request, SaveCustomerMediaProfile $save): RedirectResponse
    {
        $save->execute($request->user(), $request->validated());

        $message = match ($request->validated('action')) {
            'details' => $request->user()->fresh()->pending_email
                ? 'Your details have been saved. Check your new email address for the confirmation link, including junk/spam. Your sign-in email stays unchanged until confirmed.'
                : 'Your details have been saved.',
            'preferences' => 'Your email preferences have been saved.',
            'dancer' => 'Dancer saved. You can now add a competition in My Media.',
            'dancer_update' => 'Dancer details updated.',
            'dancer_remove' => 'Dancer removed from your profile. Your media access is unchanged.',
            'studio_request' => 'Your studio has been submitted for review. It will be attached to your account when approved.',
            'studio' => 'Studio added. Its concert photo galleries will appear in My Media when released. Competitions and concert videos require their own access codes.',
            'studio_remove' => 'Studio removed from your profile. Previously added media is unchanged.',
            default => 'Studio added. Return to My Media to access its photos and videos.',
        };

        $tab = match ($request->validated('action')) {
            'preferences' => 'preferences',
            'dancer', 'dancer_update', 'dancer_remove' => 'dancers',
            'studio', 'studio_remove', 'studio_request', 'studio_gallery' => 'studios',
            default => 'details',
        };

        return to_route('customer.profile.edit', ['tab' => $tab])->with('status', $message);
    }
}
