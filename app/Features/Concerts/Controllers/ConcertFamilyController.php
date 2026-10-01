<?php

namespace App\Features\Concerts\Controllers;

use App\Features\Concerts\Models\Concert;
use App\Features\Concerts\Models\ConcertFamilyShare;
use App\Features\Concerts\Requests\ConcertFamilyPolicyRequest;
use App\Features\Concerts\Requests\CreateConcertFamilyShareRequest;
use App\Features\Concerts\Services\ConcertFamilyAccess;
use App\Features\Concerts\Services\ConcertFamilyShares;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ConcertFamilyController extends Controller
{
    public function __construct(private readonly ConcertFamilyShares $shares, private readonly ConcertFamilyAccess $access) {}

    public function index(Request $request, Concert $concert)
    {
        return response()->view('concert-family.manage', $this->shares->ownerPage($request->user(), $concert))->header('Cache-Control', 'private, no-store');
    }

    public function store(CreateConcertFamilyShareRequest $request, Concert $concert)
    {
        $this->shares->create($request->user(), $concert, $request->validated());

        return back()->with('status', 'Family invitation created. Copy the link below and send it to your family member.');
    }

    public function renew(Request $request, Concert $concert, ConcertFamilyShare $share)
    {
        abort_unless($share->concert_id === $concert->id, 404);
        $this->shares->renew($request->user(), $share);

        return back()->with('status', 'Invitation renewed. Email verification is required again. Existing download counts are unchanged.');
    }

    public function revoke(Request $request, Concert $concert, ConcertFamilyShare $share)
    {
        abort_unless($share->concert_id === $concert->id, 404);
        abort_unless($share->user_id === $request->user()->id || $this->access->canManage($request->user(), $concert), 403);
        $this->shares->revoke($share);

        return back()->with('status', 'Family access revoked. Previously issued video links may work briefly; saved copies cannot be recalled.');
    }

    public function settings(Request $request, Concert $concert)
    {
        return response()->view('concert-family.manage', $this->shares->managerPage($request->user(), $concert))->header('Cache-Control', 'private, no-store');
    }

    public function policy(ConcertFamilyPolicyRequest $request, Concert $concert)
    {
        $this->shares->updatePolicy($request->user(), $concert, $request->validated());

        return back()->with('status', 'Studio family-sharing settings saved.');
    }
}
