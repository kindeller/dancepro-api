<?php

namespace App\Features\Customers\Services;

use App\Features\Customers\Models\CustomerAccountActivity;
use App\Features\Customers\Models\CustomerAccountNote;
use App\Features\Customers\Models\CustomerConsentHistory;
use App\Features\Customers\Models\CustomerPrivacyRequest;
use App\Features\Customers\Models\CustomerRiskEvent;
use App\Features\Orders\Models\Order;
use App\Features\Orders\Services\OrderFinancials;
use App\Features\Orders\Support\OrderStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerAccountIntelligence
{
    public function metrics(User $customer): array
    {
        $financials = app(OrderFinancials::class);
        $orders = Order::where('user_id', $customer->id)->whereIn('status', [OrderStatus::Paid->value, OrderStatus::Fulfilled->value, OrderStatus::Refunded->value])
            ->with(['items.mediaCollection.concert', 'refunds.items'])->get()->reject(fn (Order $order) => $financials->complimentary($order));
        $cash = (int) $orders->sum(fn (Order $order) => $order->paymentAmount());
        $refunds = (int) $orders->sum(fn (Order $order) => $financials->refunds($order));
        $events = $orders->flatMap(fn (Order $order) => $order->items->map(fn ($item) => $financials->event($order, $item)))
            ->filter()->map(fn (array $event) => implode(':', $event))->unique()->count();

        return ['lifetime_value' => $cash - $refunds, 'cash_collected' => $cash, 'refunds' => $refunds, 'order_count' => $orders->count(),
            'average_order_value' => $orders->count() ? intdiv($cash, $orders->count()) : 0,
            'repeat_customer' => $orders->count() > 1, 'purchased_events' => $events,
            'first_order_at' => $orders->min('paid_at'), 'last_order_at' => $orders->max('paid_at')];
    }

    public function duplicates(User $customer)
    {
        $phone = preg_replace('/\D+/', '', (string) $customer->customerProfile?->phone);

        return User::query()->where('type', 'customer')->where('id', '!=', $customer->id)->whereNull('merged_into_user_id')
            ->where(function ($query) use ($customer, $phone): void {
                $query->whereRaw('lower(name) = ?', [mb_strtolower($customer->name)]);
                if ($phone !== '') {
                    $query->orWhereHas('customerProfile', fn ($q) => $q->whereRaw("replace(replace(replace(phone,' ',''),'-',''),'(', '') like ?", ['%'.substr($phone, -8)]));
                }
            })->with('customerProfile')->withCount('orders')->limit(20)->get();
    }

    public function preferences(User $customer, User $actor, array $input): void
    {
        app(CustomerCommunicationPreferences::class)->save($customer, $input, $actor, 'admin_support', $input['reason']);
        $this->activity($customer, $actor, 'preferences_updated', 'Contact preferences updated.', ['reason' => $input['reason']]);
    }

    public function note(User $customer, User $actor, array $input): CustomerAccountNote
    {
        $note = CustomerAccountNote::create(['user_id' => $customer->id, 'created_by_user_id' => $actor->id, 'note' => $input['note'], 'tags' => collect(explode(',', (string) ($input['tags'] ?? '')))->map(fn ($v) => trim($v))->filter()->unique()->values()->all(), 'follow_up_at' => $input['follow_up_at'] ?? null]);
        $this->activity($customer, $actor, 'note_added', 'Internal note added.', ['note_uuid' => $note->uuid]);

        return $note;
    }

    public function risk(User $customer, User $actor, array $input): CustomerRiskEvent
    {
        if (($input['order_id'] ?? null) && ! Order::where('user_id', $customer->id)->whereKey($input['order_id'])->exists()) {
            throw ValidationException::withMessages(['order_id' => 'Choose one of this customer’s orders.']);
        }
        $risk = CustomerRiskEvent::create(['user_id' => $customer->id, 'order_id' => $input['order_id'] ?? null, 'recorded_by_user_id' => $actor->id, 'type' => $input['type'], 'amount' => isset($input['amount']) ? (int) round($input['amount'] * 100) : null, 'provider_reference' => $input['provider_reference'] ?? null, 'details' => $input['details'], 'occurred_at' => now()]);
        $this->activity($customer, $actor, 'risk_event_added', 'Payment risk event recorded.', ['risk_uuid' => $risk->uuid, 'type' => $risk->type]);

        return $risk;
    }

    public function export(User $customer, User $actor, string $reason): array
    {
        $request = CustomerPrivacyRequest::create(['user_id' => $customer->id, 'handled_by_user_id' => $actor->id, 'type' => 'export', 'status' => 'completed', 'reason' => $reason, 'requested_at' => now(), 'completed_at' => now()]);
        $customer->load('customerProfile', 'orders.items', 'creditTransactions');
        $data = ['generated_at' => now()->toIso8601String(), 'customer' => $customer->only(['id', 'name', 'email', 'created_at', 'last_login_at']), 'profile' => $customer->customerProfile?->toArray(), 'orders' => $customer->orders->toArray(), 'credits' => $customer->creditTransactions->toArray(), 'activities' => CustomerAccountActivity::where('user_id', $customer->id)->get()->toArray(), 'consents' => CustomerConsentHistory::where('user_id', $customer->id)->get()->toArray()];
        $data['account_records'] = app(CustomerPrivacyData::class)->export($customer);
        $this->activity($customer, $actor, 'privacy_export', 'Privacy data export generated.', ['request_uuid' => $request->uuid]);

        return $data;
    }

    public function anonymize(User $customer, User $actor, string $confirmationEmail, string $reason): void
    {
        if (mb_strtolower($confirmationEmail) !== mb_strtolower($customer->email)) {
            throw ValidationException::withMessages(['confirmation_email' => 'Enter the customer’s current email address to confirm anonymisation.']);
        }
        DB::transaction(function () use ($customer, $actor, $reason): void {
            $anonymousEmail = 'anonymised-'.$customer->id.'-'.Str::lower(Str::random(12)).'@deleted.invalid';
            $anonymousName = 'Anonymised customer '.$customer->id;
            $this->activity($customer, $actor, 'account_anonymized', 'Customer personal details anonymised.', ['reason' => $reason]);
            CustomerPrivacyRequest::create(['user_id' => $customer->id, 'handled_by_user_id' => $actor->id, 'type' => 'anonymize', 'status' => 'completed', 'reason' => $reason, 'requested_at' => now(), 'completed_at' => now()]);
            $customer->tokens()->delete();
            $customer->customerProfile?->delete();
            DB::table('customer_dancers')->where('user_id', $customer->id)->delete();
            DB::table('customer_studios')->where('user_id', $customer->id)->delete();
            DB::table('orders')->where('user_id', $customer->id)->update(['customer_email' => $anonymousEmail, 'customer_name' => $anonymousName]);
            DB::table('customer_account_emails')->where('user_id', $customer->id)->update(['recipient_email' => $anonymousEmail, 'subject' => '[redacted]', 'body' => '[redacted]', 'metadata' => null]);
            DB::table('customer_support_requests')->where('user_id', $customer->id)->update(['subject' => '[redacted]', 'message' => '[redacted]', 'response_note' => null, 'context' => null]);
            DB::table('customer_login_events')->where('user_id', $customer->id)->update(['email' => $anonymousEmail, 'ip_address' => null, 'user_agent' => null, 'device_name' => null, 'risk_reasons' => null]);
            DB::table('customer_account_notes')->where('user_id', $customer->id)->update(['note' => '[redacted]', 'tags' => null]);
            DB::table('customer_consent_histories')->where('user_id', $customer->id)->update(['reason' => null, 'snapshot' => null]);
            DB::table('customer_account_activities')->where('user_id', $customer->id)->update(['metadata' => null]);
            app(CustomerPrivacyData::class)->redact($customer, $anonymousEmail);
            $customer->update(['account_status_reason' => '[redacted]', 'name' => $anonymousName, 'email' => $anonymousEmail, 'email_verified_at' => null, 'is_active' => false, 'account_status' => 'cancelled', 'anonymized_at' => now()]);
        });
    }

    private function activity(User $customer, User $actor, string $type, string $summary, array $metadata = []): void
    {
        CustomerAccountActivity::create(['user_id' => $customer->id, 'actor_user_id' => $actor->id, 'type' => $type, 'summary' => $summary, 'metadata' => $metadata]);
    }
}
