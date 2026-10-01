<?php

namespace App\Features\Payments\Services;

use App\Features\Customers\Support\UserType;
use App\Features\Orders\Models\Order;
use App\Features\Payments\Models\CustomerCreditTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerCredits
{
    public function balance(User $user): int
    {
        return (int) CustomerCreditTransaction::query()->where('user_id', $user->id)->sum('amount');
    }

    /** @return Collection<int, User> */
    public function search(string $input): Collection
    {
        $term = trim($input);
        if ($term === '') {
            return new Collection;
        }
        $escaped = addcslashes($term, '%_\\');

        return User::query()->where('type', UserType::Customer->value)
            ->where(function ($query) use ($term, $escaped): void {
                if (ctype_digit($term)) {
                    $query->whereKey((int) $term)->orWhere('email', 'like', "%{$escaped}%");
                } else {
                    $query->where('email', 'like', "%{$escaped}%")->orWhere('name', 'like', "%{$escaped}%")->orWhere('uuid', $term);
                }
            })->withSum('creditTransactions as credit_balance', 'amount')->orderBy('email')->limit(20)->get();
    }

    public function grant(User $customer, int $amount, User $actor, string $note, string $type = 'reward'): CustomerCreditTransaction
    {
        abort_unless($customer->type === UserType::Customer->value, 404);
        $kind = match ($type) {
            'gift' => 'gift_credit',
            'purchased' => 'purchased_credit',
            default => 'reward_credit',
        };

        return DB::transaction(function () use ($customer, $amount, $actor, $note, $kind): CustomerCreditTransaction {
            User::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();

            return CustomerCreditTransaction::query()->create([
                'user_id' => $customer->id,
                'amount' => $amount,
                'kind' => $kind,
                'note' => $note,
                'created_by_user_id' => $actor->id,
            ]);
        });
    }

    public function availableForOrder(User $user, int $orderTotal, bool $useCredit): int
    {
        return $useCredit ? min($orderTotal, max(0, $this->balance($user))) : 0;
    }

    public function validateForPayment(Order $order): void
    {
        $amount = (int) ($order->metadata['account_credit_amount'] ?? 0);
        if ($amount === 0 || CustomerCreditTransaction::query()->where('order_id', $order->id)->exists()) {
            return;
        }
        User::query()->whereKey($order->user_id)->lockForUpdate()->firstOrFail();
        if ($this->balance($order->user) < $amount) {
            throw ValidationException::withMessages([
                'payment' => 'Your account credit balance changed. Return to your cart and review the updated total.',
            ]);
        }
    }

    public function redeem(Order $order): ?CustomerCreditTransaction
    {
        $amount = (int) ($order->metadata['account_credit_amount'] ?? 0);
        if ($amount === 0) {
            return null;
        }

        return CustomerCreditTransaction::query()->firstOrCreate(['order_id' => $order->id], [
            'user_id' => $order->user_id,
            'amount' => -$amount,
            'kind' => 'order_redemption',
            'note' => 'Applied to order '.$order->displayNumber(),
        ]);
    }
}
