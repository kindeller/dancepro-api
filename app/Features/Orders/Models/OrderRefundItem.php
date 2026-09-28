<?php

namespace App\Features\Orders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderRefundItem extends Model
{
    protected $guarded = ['id'];

    public function refund(): BelongsTo
    {
        return $this->belongsTo(OrderRefund::class, 'order_refund_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    protected function casts(): array
    {
        return ['amount' => 'integer', 'tax_amount' => 'integer', 'revoke_access' => 'boolean'];
    }
}
