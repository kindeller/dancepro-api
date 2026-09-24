<?php

namespace App\Features\Payments\Models;

use App\Features\Orders\Models\Order;
use App\Models\User;
use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['uuid', 'order_id', 'user_id', 'provider', 'idempotency_key', 'amount', 'currency', 'status', 'provider_transaction_id', 'failure_code', 'failure_message', 'processed_at', 'metadata'])]
class PaymentAttempt extends Model
{
    use HasPublicUuid;

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return ['amount' => 'integer', 'processed_at' => 'datetime', 'metadata' => 'array'];
    }
}
