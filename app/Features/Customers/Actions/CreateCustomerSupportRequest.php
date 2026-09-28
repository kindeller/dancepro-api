<?php

namespace App\Features\Customers\Actions;

use App\Features\Customers\Models\CustomerSupportRequest;
use App\Features\Customers\Services\CustomerConversations;
use App\Features\Customers\Services\CustomerInbox;
use App\Features\Orders\Models\Order;
use App\Features\Orders\Models\OrderMediaEntitlement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateCustomerSupportRequest
{
    public function execute(User $user, array $input): CustomerSupportRequest
    {
        $order = filled($input['order_id'] ?? null)
            ? Order::query()->where('user_id', $user->id)->find($input['order_id'])
            : null;
        if (filled($input['order_id'] ?? null) && ! $order) {
            throw ValidationException::withMessages(['order_id' => 'Choose one of your own orders.']);
        }

        $entitlement = filled($input['order_media_entitlement_id'] ?? null)
            ? OrderMediaEntitlement::query()->with('orderItem.order')
                ->whereHas('orderItem.order', fn ($query) => $query->where('user_id', $user->id))
                ->find($input['order_media_entitlement_id'])
            : null;
        if (filled($input['order_media_entitlement_id'] ?? null) && ! $entitlement) {
            throw ValidationException::withMessages(['order_media_entitlement_id' => 'Choose media from one of your own orders.']);
        }
        if ($input['type'] === 'download_reactivation') {
            if ($entitlement?->revoked_at) {
                throw ValidationException::withMessages(['order_media_entitlement_id' => 'Revoked media access cannot be reactivated.']);
            }
            if (! $entitlement?->ready_at || ! $entitlement->expires_at?->isPast()) {
                throw ValidationException::withMessages(['order_media_entitlement_id' => 'Reactivation is available after a ready download period has ended.']);
            }
            if (CustomerSupportRequest::query()->where('user_id', $user->id)
                ->where('order_media_entitlement_id', $entitlement->id)->where('type', 'download_reactivation')
                ->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['order_media_entitlement_id' => 'A reactivation request for this media is already waiting for review.']);
            }
            $order = $entitlement->orderItem->order;
        }

        $event = filled($input['event_uuid'] ?? null)
            ? app(CustomerInbox::class)->events($user)->where('uuid', $input['event_uuid'])->first()
            : null;
        if (filled($input['event_uuid'] ?? null) && ! $event) {
            throw ValidationException::withMessages(['event_uuid' => 'Choose one of your own events.']);
        }

        return DB::transaction(function () use ($user, $input, $order, $entitlement, $event): CustomerSupportRequest {
            $request = CustomerSupportRequest::query()->create([
                'user_id' => $user->id,
                'order_id' => $order?->id,
                'order_media_entitlement_id' => $entitlement?->id,
                'type' => $input['type'],
                'subject' => $input['subject'],
                'message' => $input['message'],
                'status' => 'pending',
                'requested_at' => now(),
                'context' => array_filter([
                    'customer_email' => $user->email,
                    'event_uuid' => $event?->uuid, 'event_name' => $event?->name,
                    'order_uuid' => $order?->uuid,
                    'order_number' => $order?->displayNumber(),
                    'order_status' => $order?->status?->value,
                    'item_name' => $entitlement?->orderItem?->snapshot_display_name,
                    'media_type' => $entitlement?->media_type,
                    'ready_at' => $entitlement?->ready_at?->toIso8601String(),
                    'expired_at' => $entitlement?->expires_at?->toIso8601String(),
                    'download_count' => $entitlement?->download_count,
                ], fn ($value) => $value !== null),
            ]);
            app(CustomerConversations::class)->attach($request->messages()->firstOrFail(), $input['attachment'] ?? null);

            return $request;
        });
    }
}
