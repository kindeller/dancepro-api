<?php

namespace App\Features\Customers\Services;

use App\Features\Customers\Models\CustomerConcertPhotoSelection;
use App\Features\Media\Models\MediaAsset;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerAccountMediaPreview
{
    public function response(User $customer, MediaAsset $asset): StreamedResponse
    {
        abort_unless($customer->type === 'customer', 404);
        abort_unless(CustomerConcertPhotoSelection::query()
            ->where('user_id', $customer->id)
            ->where('media_asset_id', $asset->id)
            ->where(fn ($query) => $query->where('is_favourite', true)->orWhere('in_cart', true))
            ->exists(), 404);
        abort_unless(Storage::disk($asset->storage_disk)->exists($asset->storage_key), 404);

        return Storage::disk($asset->storage_disk)->response(
            $asset->storage_key,
            $asset->display_name ?: $asset->original_filename,
            ['Content-Type' => $asset->mime_type ?: 'application/octet-stream', 'Cache-Control' => 'private, no-store'],
            'inline',
        );
    }
}
