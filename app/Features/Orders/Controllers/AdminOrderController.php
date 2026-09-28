<?php

namespace App\Features\Orders\Controllers;

use App\Features\Media\Models\MediaAsset;
use App\Features\Orders\Models\Order;
use App\Features\Orders\Models\OrderItem;
use App\Features\Orders\Requests\AdminOrdersRequest;
use App\Features\Orders\Services\AdminOrderDetail;
use App\Features\Orders\Services\AdminOrderWorkspace;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminOrderController extends Controller
{
    public function index(AdminOrdersRequest $request, AdminOrderWorkspace $workspace): Response
    {
        return response()->view('admin.orders.index', $workspace->build($request->validated()))->header('Cache-Control', 'private, no-store');
    }

    public function show(Order $order, AdminOrderDetail $details): Response
    {
        return response()->view('admin.orders.show', $details->build($order))
            ->header('Cache-Control', 'private, no-store');
    }

    public function thumbnail(Order $order, OrderItem $item): StreamedResponse
    {
        abort_unless($item->order_id === $order->id, 404);
        $asset = $item->mediaAsset;
        if (! $asset) {
            $asset = MediaAsset::query()->whereIn('uuid', data_get($item->metadata, 'asset_uuids', []))
                ->where('media_type', 'photo')->orderBy('sort_order')->first();
        }
        abort_unless($asset, 404);
        $disk = $asset->thumbnail_storage_key ? $asset->thumbnail_storage_disk : $asset->storage_disk;
        $key = $asset->thumbnail_storage_key ?: $asset->storage_key;
        abort_unless($disk && $key && Storage::disk($disk)->exists($key), 404);

        return Storage::disk($disk)->response($key, $asset->original_filename,
            ['Content-Type' => $asset->thumbnail_storage_key ? 'image/jpeg' : ($asset->mime_type ?: 'image/jpeg'),
                'Cache-Control' => 'private, max-age=300'], 'inline');
    }
}
