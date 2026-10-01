<?php

namespace App\Features\Concerts\Controllers;

use App\Features\Concerts\Models\ConcertFamilyDownload;
use App\Features\Concerts\Models\ConcertFamilyShare;
use App\Features\Concerts\Requests\ConcertFamilyActivityRequest;
use App\Features\Concerts\Requests\ConcertFamilyDownloadRequest;
use App\Features\Concerts\Requests\ConcertFamilyPlaybackRequest;
use App\Features\Concerts\Requests\VerifyConcertFamilyShareRequest;
use App\Features\Concerts\Services\ConcertFamilyAccess;
use App\Features\Concerts\Services\ConcertFamilyDelivery;
use App\Features\Concerts\Services\ConcertFamilyShares;
use App\Features\Media\Models\MediaAsset;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SharedConcertController extends Controller
{
    public function __construct(private readonly ConcertFamilyShares $shares, private readonly ConcertFamilyAccess $access, private readonly ConcertFamilyDelivery $delivery) {}

    public function show(Request $request, ConcertFamilyShare $share)
    {
        $available = $this->access->available($share);
        $verified = $this->access->verified($request, $share);

        return response()->view('concert-family.guest', ['share' => $share, 'available' => $available, 'verified' => $verified,
            'videos' => $verified ? $share->videos()->whereIn('media_asset_id', $this->access->assets($share->concert)->select('id'))->with('asset')->get() : collect(),
            'access' => $this->access])->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow')->header('Referrer-Policy', 'no-referrer');
    }

    public function sendCode(Request $request, ConcertFamilyShare $share)
    {
        $this->shares->sendCode($request, $share);

        return back()->with('status', 'A verification code has been sent to the invited email address. It expires in 10 minutes.');
    }

    public function verify(VerifyConcertFamilyShareRequest $request, ConcertFamilyShare $share)
    {
        $this->shares->verify($request, $share, $request->validated('code'));

        return to_route('concert-family.show', $share);
    }

    public function playback(ConcertFamilyPlaybackRequest $request, ConcertFamilyShare $share, MediaAsset $asset)
    {
        return $this->delivery->playback($request, $share, $asset);
    }

    public function media(ConcertFamilyPlaybackRequest $request, ConcertFamilyShare $share, MediaAsset $asset)
    {
        return $this->delivery->media($request, $share, $asset);
    }

    public function requestDownload(ConcertFamilyDownloadRequest $request, ConcertFamilyShare $share, MediaAsset $asset)
    {
        $download = $this->delivery->requestDownload($request, $share, $asset, $request->validated('request_uuid'));
        if ($request->expectsJson()) {
            return response()->json(['url' => route('concert-family.download', [$share, $download]), 'remaining' => 3 - $download->video->download_requests])->header('Cache-Control', 'private, no-store');
        }

        return to_route('concert-family.download', [$share, $download]);
    }

    public function download(Request $request, ConcertFamilyShare $share, ConcertFamilyDownload $download)
    {
        return $this->delivery->download($request, $share, $download);
    }

    public function activity(ConcertFamilyActivityRequest $request, ConcertFamilyShare $share, MediaAsset $asset)
    {
        $this->delivery->activity($request, $share, $asset, $request->validated());

        return response()->noContent();
    }
}
