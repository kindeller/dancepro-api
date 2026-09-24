<?php

namespace App\Features\Customers\Services;

use App\Features\Customers\Models\CustomerCompetitionRegistration;
use App\Features\Customers\Models\CustomerConcertPhotoSelection;
use App\Features\Customers\Models\CustomerMediaSelection;
use App\Features\Customers\Models\CustomerStudio;
use App\Features\Customers\Models\GuestGalleryAccess;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransferGuestGalleryActivity
{
    public function execute(Request $request, User $user): void
    {
        if (! $user->canAccessCustomer()) {
            return;
        }
        $emailHash = hash('sha256', mb_strtolower(trim($user->email)));
        $ids = $request->session()->get('guest_gallery_accesses', []);
        $accesses = GuestGalleryAccess::whereIn('uuid', $ids)->where('email_hash', $emailHash)
            ->with('concert', 'selections')->get();
        DB::transaction(function () use ($accesses, $user): void {
            foreach ($accesses as $access) {
                if ($access->gallery_type === 'competition' && $access->scheduling_event_id) {
                    CustomerCompetitionRegistration::updateOrCreate([
                        'user_id' => $user->id, 'scheduling_event_id' => $access->scheduling_event_id,
                    ], ['access_verified_at' => now()]);
                    foreach ($access->selections as $selection) {
                        $customerSelection = CustomerMediaSelection::firstOrNew([
                            'user_id' => $user->id, 'scheduling_event_id' => $access->scheduling_event_id,
                            'selection_key' => $selection->selection_key,
                        ]);
                        $customerSelection->fill(['product' => $selection->product, 'item_uuid' => $selection->item_uuid,
                            'photo_uuid' => $selection->photo_uuid,
                            'is_favourite' => $customerSelection->is_favourite || $selection->is_favourite,
                            'in_cart' => $customerSelection->in_cart || $selection->in_cart])->save();
                    }
                } elseif ($access->gallery_type === 'concert' && $access->concert) {
                    CustomerStudio::updateOrCreate([
                        'user_id' => $user->id, 'studio_id' => $access->concert->studio_id,
                    ], ['access_verified_at' => now()]);
                    foreach ($access->selections as $selection) {
                        $customerSelection = CustomerConcertPhotoSelection::firstOrNew([
                            'user_id' => $user->id, 'media_asset_id' => $selection->media_asset_id,
                        ]);
                        $customerSelection->fill(['studio_id' => $access->concert->studio_id,
                            'concert_id' => $access->concert_id,
                            'is_favourite' => $customerSelection->is_favourite || $selection->is_favourite,
                            'in_cart' => $customerSelection->in_cart || $selection->in_cart])->save();
                    }
                }
                $access->delete();
            }
        });
        $transferred = $accesses->pluck('uuid')->all();
        $request->session()->put('guest_gallery_accesses', array_values(array_diff($ids, $transferred)));
    }
}
