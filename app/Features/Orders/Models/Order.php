<?php

namespace App\Features\Orders\Models;

use App\Features\Orders\Support\OrderStatus;
use App\Models\User;
use App\Shared\Models\HasPublicUuid;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['uuid', 'user_id', 'customer_email', 'customer_name', 'status', 'currency', 'subtotal_amount', 'total_amount', 'placed_at', 'paid_at', 'fulfilled_at', 'cancelled_at', 'metadata'])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory, HasPublicUuid, SoftDeletes;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class, 'subtotal_amount' => 'integer', 'total_amount' => 'integer',
            'placed_at' => 'datetime', 'paid_at' => 'datetime', 'fulfilled_at' => 'datetime',
            'cancelled_at' => 'datetime', 'metadata' => 'array',
        ];
    }

    protected static function newFactory(): OrderFactory
    {
        return OrderFactory::new();
    }
}
