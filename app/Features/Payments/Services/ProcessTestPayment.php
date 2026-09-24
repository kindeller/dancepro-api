<?php

namespace App\Features\Payments\Services;

use App\Features\Orders\Models\Order;
use App\Features\Orders\Support\OrderStatus;
use App\Features\Payments\Contracts\PaymentGateway;
use App\Features\Payments\Models\PaymentAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProcessTestPayment
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly ActivatePaidOrder $activatePaidOrder,
        private readonly ValidatePayableOrder $validatePayableOrder,
        private readonly OrderDiscounts $orderDiscounts,
        private readonly CustomerCredits $customerCredits,
        private readonly CustomerMediaNotifications $mediaNotifications,
    ) {}

    public function execute(User $user, Order $order, string $idempotencyKey, string $outcome): PaymentAttempt
    {
        return DB::transaction(function () use ($user, $order, $idempotencyKey, $outcome): PaymentAttempt {
            $existing = PaymentAttempt::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                abort_unless($existing->user_id === $user->id && $existing->order_id === $order->id, 404);

                return $existing;
            }

            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($order->user_id === $user->id, 404);
            if ($order->status !== OrderStatus::Draft) {
                throw ValidationException::withMessages(['payment' => 'This order has already been processed.']);
            }
            $this->validatePayableOrder->execute($order);
            $this->orderDiscounts->validateForPayment($order);
            $this->customerCredits->validateForPayment($order);

            $result = $this->gateway->charge($order, $idempotencyKey, $outcome);
            $attempt = PaymentAttempt::create([
                'order_id' => $order->id,
                'user_id' => $user->id,
                'provider' => $this->gateway->name(),
                'idempotency_key' => $idempotencyKey,
                'amount' => $order->paymentAmount(),
                'currency' => $order->currency,
                'status' => $result->approved ? 'approved' : 'declined',
                'provider_transaction_id' => $result->transactionId,
                'failure_code' => $result->failureCode,
                'failure_message' => $result->failureMessage,
                'processed_at' => now(),
                'metadata' => ['mode' => 'test'],
            ]);

            if ($result->approved) {
                $order->update(['status' => OrderStatus::Paid, 'placed_at' => now(), 'paid_at' => now()]);
                $this->orderDiscounts->record($order);
                $this->customerCredits->redeem($order);
                $this->activatePaidOrder->execute($order);
                $this->mediaNotifications->receipt($order->fresh());
            }

            return $attempt;
        });
    }
}
