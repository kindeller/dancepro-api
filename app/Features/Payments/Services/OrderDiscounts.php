<?php

namespace App\Features\Payments\Services;

use App\Features\Media\Models\MediaDiscountCode;
use App\Features\Media\Models\MediaDiscountRedemption;
use App\Features\Orders\Models\Order;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class OrderDiscounts
{
    /** @return array{id: int, code: string, type: string, percent: ?int, configured_amount: ?int, amount: int}|null */
    public function quote(?string $input, User $user, int $amount): ?array
    {
        if (blank($input)) {
            return null;
        }
        $code = MediaDiscountCode::query()->where('code', strtoupper(trim($input)))->first();
        if (! $code) {
            $this->invalid();
        }
        $this->validate($code, $user);

        return ['id' => $code->id, 'code' => $code->code, 'type' => $code->discount_type,
            'percent' => $code->percent, 'configured_amount' => $code->amount,
            'amount' => $code->discount_type === 'fixed' ? min($amount, (int) $code->amount) : intdiv($amount * (int) $code->percent, 100)];
    }

    public function validateForPayment(Order $order): void
    {
        $id = $order->metadata['discount_code_id'] ?? null;
        if (! $id) {
            return;
        }
        $code = MediaDiscountCode::query()->whereKey($id)->lockForUpdate()->first();
        if (! $code || ! $order->user) {
            $this->invalid();
        }
        $this->validate($code, $order->user);
        if ($code->code !== ($order->metadata['discount_code'] ?? null)
            || $code->discount_type !== ($order->metadata['discount_code_type'] ?? null)
            || $code->percent !== ($order->metadata['discount_code_percent'] ?? null)
            || $code->amount !== ($order->metadata['discount_code_configured_amount'] ?? null)) {
            throw ValidationException::withMessages(['discount_code' => 'This discount code changed. Return to your cart and review the updated total.']);
        }
    }

    public function record(Order $order): void
    {
        if (! ($order->metadata['discount_code_id'] ?? null)) {
            return;
        }
        MediaDiscountRedemption::firstOrCreate(['order_id' => $order->id], [
            'media_discount_code_id' => $order->metadata['discount_code_id'],
            'user_id' => $order->user_id,
            'code' => $order->metadata['discount_code'],
            'discount_type' => $order->metadata['discount_code_type'],
            'percent' => $order->metadata['discount_code_percent'],
            'configured_amount' => $order->metadata['discount_code_configured_amount'],
            'amount' => $order->metadata['discount_code_amount'],
            'redeemed_at' => now(),
        ]);
    }

    private function validate(MediaDiscountCode $code, User $user): void
    {
        if (! $code->enabled || ($code->starts_at && $code->starts_at->isFuture())
            || ($code->ends_at && $code->ends_at->isPast())) {
            $this->invalid();
        }
        $query = MediaDiscountRedemption::query()->where('media_discount_code_id', $code->id);
        if ($code->max_redemptions !== null && (clone $query)->count() >= $code->max_redemptions) {
            throw ValidationException::withMessages(['discount_code' => 'This discount code has reached its usage limit.']);
        }
        if ($code->max_redemptions_per_customer !== null
            && (clone $query)->where('user_id', $user->id)->count() >= $code->max_redemptions_per_customer) {
            throw ValidationException::withMessages(['discount_code' => 'You have already used this discount code.']);
        }
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['discount_code' => 'Enter a valid active discount code.']);
    }
}
