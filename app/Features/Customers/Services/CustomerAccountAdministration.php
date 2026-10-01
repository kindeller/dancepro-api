<?php

namespace App\Features\Customers\Services;

use App\Features\Concerts\Models\Concert;
use App\Features\Concerts\Models\ConcertAccessGrant;
use App\Features\Customers\Models\CustomerAccountActivity;
use App\Features\Customers\Models\CustomerAccountEmail;
use App\Features\Customers\Models\CustomerCompetitionRegistration;
use App\Features\Customers\Models\CustomerSupportRequest;
use App\Features\Customers\Notifications\CustomerAccountMessage;
use App\Features\Orders\Models\Order;
use App\Features\Orders\Models\OrderRefund;
use App\Features\Orders\Support\OrderStatus;
use App\Features\Payments\Services\CustomerCredits;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerAccountAdministration
{
    public function __construct(private readonly CustomerCredits $credits) {}

    public function status(User $customer, User $actor, string $status, string $reason): void
    {
        abort_unless($customer->type === 'customer', 404);
        abort_if($status === 'active' && ($customer->anonymized_at || $customer->merged_into_user_id), 409, 'A merged or anonymised account cannot be reactivated.');
        DB::transaction(function () use ($customer, $actor, $status, $reason): void {
            $customer->update([
                'account_status' => $status,
                'is_active' => $status === 'active',
                'paused_at' => $status === 'paused' ? now() : null,
                'cancelled_at' => $status === 'cancelled' ? now() : null,
                'account_status_reason' => $reason,
                'account_status_changed_by_user_id' => $actor->id,
            ]);
            $customer->tokens()->delete();
            $this->activity($customer, $actor, 'account_status', 'Account changed to '.$status.'.', ['reason' => $reason]);
        });
    }

    public function contact(User $customer, User $actor, array $input): void
    {
        abort_unless($customer->type === 'customer', 404);
        abort_if($customer->anonymized_at || $customer->merged_into_user_id, 409, 'A merged or anonymised account cannot be edited.');
        DB::transaction(function () use ($customer, $actor, $input): void {
            $before = ['name' => $customer->name, 'email' => $customer->email,
                'preferred_name' => $customer->customerProfile?->preferred_name, 'phone' => $customer->customerProfile?->phone];
            $emailChanged = $customer->email !== $input['email'];
            $customer->update([
                'name' => $input['name'],
                ...array_intersect_key($input, array_flip(['first_name', 'last_name'])),
                'email' => $input['email'],
                'email_verified_at' => $emailChanged ? null : $customer->email_verified_at,
            ]);
            $customer->customerProfile()->updateOrCreate(['user_id' => $customer->id], [
                'preferred_name' => $input['preferred_name'] ?? null,
                'phone' => $input['phone'] ?? null,
                'registration_source' => $customer->customerProfile?->registration_source ?? 'admin_support',
            ]);
            if ($emailChanged) {
                $customer->tokens()->delete();
            }
            $this->activity($customer, $actor, 'contact_details_updated', 'Customer contact details updated.', [
                'before' => $before,
                'after' => collect($input)->only(['name', 'email', 'preferred_name', 'phone'])->all(),
                'reason' => $input['reason'],
                'email_requires_verification' => $emailChanged,
            ]);
        });
    }

    public function passwordResetRequested(User $customer, User $actor): void
    {
        $this->activity($customer, $actor, 'password_reset_requested', 'Password reset email requested by staff.');
    }

    public function credit(User $customer, User $actor, int $amount, string $type, string $note): void
    {
        $transaction = $this->credits->grant($customer, $amount, $actor, $note, $type);
        $this->activity($customer, $actor, 'credit_added', '$'.number_format($amount / 100, 2).' account credit added.', ['transaction_uuid' => $transaction->uuid, 'note' => $note]);
    }

    public function event(User $customer, User $actor, string $kind, string $uuid, string $reason): void
    {
        if ($kind === 'competition') {
            $event = SchedulingEvent::where('uuid', $uuid)->where('event_type', 'competition')->firstOrFail();
            CustomerCompetitionRegistration::updateOrCreate(
                ['user_id' => $customer->id, 'scheduling_event_id' => $event->id],
                ['access_verified_at' => now()],
            );
            $label = $event->name;
            $subjectId = $event->id;
        } else {
            $concert = Concert::where('uuid', $uuid)->firstOrFail();
            ConcertAccessGrant::updateOrCreate(['concert_id' => $concert->id, 'user_id' => $customer->id, 'source' => 'admin_support'], [
                'email' => $customer->email, 'status' => 'active', 'granted_by_user_id' => $actor->id,
                'claimed_at' => now(), 'expires_at' => now()->addYear(), 'revoked_at' => null, 'revoke_reason' => null, 'metadata' => ['reason' => $reason],
            ]);
            $label = $concert->name;
            $subjectId = $concert->id;
        }
        $this->activity($customer, $actor, 'event_access_added', $label.' added manually.', ['kind' => $kind, 'reason' => $reason, 'subject_id' => $subjectId]);
    }

    public function email(User $customer, User $actor, string $subject, string $body, ?UploadedFile $attachment = null, string $audience = 'customer'): CustomerAccountEmail
    {
        $email = CustomerAccountEmail::create([
            'user_id' => $customer->id, 'sent_by_user_id' => $actor->id, 'recipient_email' => $customer->email,
            'subject' => $subject, 'body' => $body, 'status' => 'queued', 'queued_at' => now(),
        ]);
        $thread = CustomerSupportRequest::where('customer_account_email_id', $email->id)->firstOrFail();
        $thread->update(['context' => array_merge($thread->context ?? [], ['message_audience' => $audience])]);
        if ($attachment) {
            app(CustomerConversations::class)->attach($thread->messages()->firstOrFail(), $attachment);
        }
        try {
            $customer->notify(new CustomerAccountMessage($subject, $body, $thread->uuid));
            $email->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (\Throwable $exception) {
            report($exception);
            $email->update(['status' => 'failed', 'failed_at' => now(), 'metadata' => ['error' => $exception->getMessage()]]);
            throw ValidationException::withMessages(['email' => 'The message could not be handed to the configured mail service.']);
        }
        $this->activity($customer, $actor, 'email_sent', 'Email sent: '.$subject, ['email_uuid' => $email->uuid]);

        return $email;
    }

    public function refund(Order $order, User $actor, array $itemIds, string $reason, bool $revokeAccess): OrderRefund
    {
        return DB::transaction(function () use ($order, $actor, $itemIds, $reason, $revokeAccess): OrderRefund {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($order->user_id && in_array($order->status, [OrderStatus::Paid, OrderStatus::Fulfilled], true), 409);
            $items = $order->items()->whereIn('id', $itemIds)->with('mediaEntitlements')->get();
            if ($items->count() !== count(array_unique($itemIds))) {
                throw ValidationException::withMessages(['order_item_ids' => 'One or more selected products do not belong to this order.']);
            }
            $already = DB::table('order_refund_items')->join('order_refunds', 'order_refunds.id', '=', 'order_refund_items.order_refund_id')
                ->whereIn('order_refunds.status', ['pending_provider', 'processing', 'completed'])
                ->whereIn('order_refund_items.order_item_id', $items->pluck('id'))->exists();
            if ($already) {
                throw ValidationException::withMessages(['order_item_ids' => 'One or more selected products already have an active or completed refund.']);
            }
            $amount = $items->sum(fn ($item): int => (int) data_get($item->metadata, 'net_after_account_credit', $item->total_price_amount));
            $tax = $items->sum('tax_amount');
            if ($amount <= 0) {
                throw ValidationException::withMessages(['order_item_ids' => 'The selected products have no paid amount to refund.']);
            }
            $refund = OrderRefund::create([
                'order_id' => $order->id, 'requested_by_user_id' => $actor->id, 'status' => 'pending_provider',
                'amount' => $amount, 'tax_amount' => $tax, 'reason' => $reason, 'requested_at' => now(),
                'metadata' => ['provider_required' => true],
            ]);
            foreach ($items as $item) {
                $refund->items()->create([
                    'order_item_id' => $item->id,
                    'amount' => (int) data_get($item->metadata, 'net_after_account_credit', $item->total_price_amount),
                    'tax_amount' => $item->tax_amount,
                    'revoke_access' => $revokeAccess,
                ]);
            }
            $this->activity($order->user, $actor, 'refund_requested', '$'.number_format($amount / 100, 2).' refund prepared for order '.$order->displayNumber().'.', ['refund_uuid' => $refund->uuid, 'reason' => $reason]);

            return $refund;
        });
    }

    private function activity(User $customer, User $actor, string $type, string $summary, array $metadata = []): CustomerAccountActivity
    {
        return CustomerAccountActivity::create(['user_id' => $customer->id, 'actor_user_id' => $actor->id, 'type' => $type, 'summary' => $summary, 'metadata' => $metadata]);
    }
}
