<?php

namespace App\Features\Families\Controllers;

use App\Features\Families\Models\CustomerFamilyMember;
use App\Features\Families\Requests\SaveFamilyMemberRequest;
use App\Features\Families\Services\CustomerFamilies;
use App\Features\Families\Services\FamilyInvitationPrefill;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CustomerFamilyController extends Controller
{
    public function store(SaveFamilyMemberRequest $request, CustomerFamilies $families): RedirectResponse
    {
        return to_route('customer.profile.edit', ['tab' => 'family'])->with('status', $families->change($request->user(), $request->validated()));
    }

    public function invitation(Request $request, CustomerFamilyMember $member, string $token, CustomerFamilies $families, FamilyInvitationPrefill $prefill): Response
    {
        $member = $families->invitation($member, $token);
        $prefill->remember($request->session(), $member);
        // Retain the invitation through sign-in, account creation and verification.
        $request->session()->put('family_invitation_url', $request->url());
        if (! $request->user()) {
            $request->session()->put('url.intended', $request->url());
        }

        return response()->view('customers.family.invitation', compact('member', 'token'))
            ->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function accept(Request $request, CustomerFamilyMember $member, string $token, CustomerFamilies $families): RedirectResponse
    {
        $families->accept($request->user(), $member, $token);
        $request->session()->forget(['family_invitation_url', 'family_invitation_prefill']);

        return to_route('customer.profile.edit', ['tab' => 'family'])->with('status', 'You have joined the family. Your eligible purchases are now shared according to the permissions shown below.');
    }
}
