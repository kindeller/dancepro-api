<?php

namespace App\Features\Orders\Actions;

use App\Features\Media\Models\MediaAsset;
use App\Features\Orders\Models\OrderMediaAssetDownload;
use App\Features\Orders\Models\OrderMediaEntitlement;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RecordPurchasedMediaDownload
{
    public function execute(User $user, OrderMediaEntitlement $entitlement, MediaAsset $asset, Request $request, string $channel = 'web'): OrderMediaAssetDownload
    {
        return DB::transaction(function () use ($user, $entitlement, $asset, $request, $channel): OrderMediaAssetDownload {
            $download = OrderMediaAssetDownload::query()->lockForUpdate()->firstOrNew([
                'user_id' => $user->id,
                'order_media_entitlement_id' => $entitlement->id,
                'media_asset_id' => $asset->id,
            ]);
            $now = now();
            $download->fill([
                'user_id' => $user->id,
                'channel' => $channel,
                'download_count' => $download->download_count + 1,
                'bytes_requested' => $download->bytes_requested + max(0, (int) $asset->size_bytes),
                'first_download_at' => $download->first_download_at ?? $now,
                'last_download_at' => $now,
                'last_user_agent' => str($request->userAgent())->limit(512)->toString() ?: null,
                'last_ip_hash' => $request->ip() ? hash('sha256', $request->ip().'|'.config('app.key')) : null,
            ])->save();

            return $download;
        });
    }
}
