<?php

namespace App\Features\Payments\Controllers;

use App\Features\Downloads\Services\IssuePurchasedMediaDownload;
use App\Features\Media\Models\MediaAsset;
use App\Features\Orders\Actions\RecordPurchasedMediaDownload;
use App\Features\Orders\Models\OrderMediaEntitlement;
use App\Features\Orders\Services\AuthorizePurchasedMediaDownload;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CustomerPurchasedMediaDownloadController extends Controller
{
    public function __invoke(
        Request $request,
        OrderMediaEntitlement $entitlement,
        MediaAsset $asset,
        AuthorizePurchasedMediaDownload $authorize,
        IssuePurchasedMediaDownload $issue,
        RecordPurchasedMediaDownload $record,
    ): RedirectResponse {
        try {
            $asset = $authorize->execute($request->user(), $entitlement, $asset);
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() !== 410) {
                throw $exception;
            }

            $entitlement->loadMissing('orderItem.order');

            if ($entitlement->orderItem?->order?->user_id !== $request->user()->id) {
                return to_route('customer.media.help')->with('status', 'This shared purchase has expired. Ask the original purchaser to request download reactivation through Help.');
            }

            return to_route('customer.media.help', [
                'type' => 'download_reactivation',
                'order' => $entitlement->orderItem?->order_id,
                'entitlement' => $entitlement->id,
            ])->with('status', 'This download period has ended. You can request reactivation below.');
        }
        $handoff = $issue->execute($request->user(), $entitlement, $asset);
        $record->execute($request->user(), $entitlement, $asset, $request);

        $entitlement->forceFill([
            'first_download_at' => $entitlement->first_download_at ?? now(),
            'last_download_at' => now(),
            'download_count' => $entitlement->download_count + 1,
        ])->save();

        return redirect()->route('downloads.public.show', $handoff['token']);
    }
}
