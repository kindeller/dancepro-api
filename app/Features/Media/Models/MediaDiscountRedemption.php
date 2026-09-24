<?php

namespace App\Features\Media\Models;

use App\Features\Orders\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaDiscountRedemption extends Model
{
    protected $guarded = ['id'];

    public function discountCode(): BelongsTo
    {
        return $this->belongsTo(MediaDiscountCode::class, 'media_discount_code_id');
    }

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
        return ['percent' => 'integer', 'configured_amount' => 'integer', 'amount' => 'integer', 'redeemed_at' => 'datetime'];
    }
}
