<?php

namespace App\Features\Customers\Actions;

use App\Features\Customers\Models\CustomerSupportRequest;
use App\Features\Customers\Notifications\CustomerSupportRequestUpdated;
use App\Features\Orders\Models\OrderMediaEntitlementExtensionAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResolveCustomerSupportRequest
{
    public function execute(CustomerSupportRequest $supportRequest, User $actor, array $input): CustomerSupportRequest
    {
        $resolved = DB::transaction(function () use ($supportRequest, $actor, $input): CustomerSupportRequest {
            $request = CustomerSupportRequest::query()->lockForUpdate()->findOrFail($supportRequest->id);
            if ($request->status !== 'pending') {
                throw ValidationException::withMessages(['decision' => 'This request has already been reviewed.']);
            }

            if ($input['decision'] === 'approved') {
                if ($request->type !== 'download_reactivation' || ! $request->order_media_entitlement_id) {
                    throw ValidationException::withMessages(['decision' => 'Only download reactivation requests can be approved.']);
                }
                $entitlement = $request->entitlement()->lockForUpdate()->firstOrFail();
                $previousExpiry = $entitlement->expires_at;
                $newExpiry = now()->addDays((int) $input['extension_days'])->endOfDay();
                $entitlement->update(['expires_at' => $newExpiry]);
                OrderMediaEntitlementExtensionAudit::query()->create([
                    'order_media_entitlement_id' => $entitlement->id,
                    'customer_support_request_id' => $request->id,
                    'actor_user_id' => $actor->id,
                    'previous_expires_at' => $previousExpiry,
                    'new_expires_at' => $newExpiry,
                    'extension_days' => (int) $input['extension_days'],
                    'reason' => $input['response_note'] ?? null,
                ]);
            }

            $request->update([
                'status' => $input['decision'],
                'decided_by_user_id' => $actor->id,
                'response_note' => $input['response_note'] ?? null,
                'decided_at' => now(),
            ]);

            return $request->fresh(['user', 'entitlement']);
        });

        $resolved->user?->notify((new CustomerSupportRequestUpdated($resolved))->afterCommit());

        return $resolved;
    }
}
