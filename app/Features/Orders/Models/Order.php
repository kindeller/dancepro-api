<?php

namespace App\Features\Orders\Models;

use App\Features\Orders\Support\OrderStatus;
use App\Features\Payments\Models\CustomerMediaNotification;
use App\Features\Payments\Models\PaymentAttempt;
use App\Models\User;
use App\Shared\Models\HasPublicUuid;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['uuid', 'user_id', 'customer_email', 'customer_name', 'status', 'currency', 'subtotal_amount', 'total_amount', 'tax_amount', 'placed_at', 'paid_at', 'fulfilled_at', 'cancelled_at', 'metadata'])]
class Order extends Model
{
    public const DISPLAY_NUMBER_OFFSET = 100000;

    /** @use HasFactory<OrderFactory> */
    use HasFactory, HasPublicUuid, SoftDeletes;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(OrderRefund::class)->latest('requested_at');
    }

    public function paymentAttempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class)->latest('processed_at');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(CustomerMediaNotification::class)->latest('queued_at');
    }

    public function paymentAmount(): int
    {
        return (int) ($this->metadata['payment_amount'] ?? $this->total_amount);
    }

    public function displayNumber(): string
    {
        return (string) (self::DISPLAY_NUMBER_OFFSET + (int) $this->getKey());
    }

    public static function idFromDisplayNumber(string $value): ?int
    {
        if (! preg_match('/^(?:order\s*)?#?\s*(\d+)$/i', trim($value), $matches)) {
            return null;
        }

        $id = (int) $matches[1] - self::DISPLAY_NUMBER_OFFSET;

        return $id > 0 ? $id : null;
    }

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class, 'subtotal_amount' => 'integer', 'total_amount' => 'integer', 'tax_amount' => 'integer',
            'placed_at' => 'datetime', 'paid_at' => 'datetime', 'fulfilled_at' => 'datetime',
            'cancelled_at' => 'datetime', 'metadata' => 'array',
        ];
    }

    protected static function newFactory(): OrderFactory
    {
        return OrderFactory::new();
    }
}
