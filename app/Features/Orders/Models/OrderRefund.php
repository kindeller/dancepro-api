<?php

namespace App\Features\Orders\Models;

use App\Models\User;
use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderRefund extends Model
{
    public function displayNumber(): string
    {
        return 'R'.(100000 + $this->id);
    }

    use HasPublicUuid;

    protected $guarded = ['id'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderRefundItem::class);
    }

    protected function casts(): array
    {
        return ['amount' => 'integer', 'tax_amount' => 'integer', 'requested_at' => 'datetime', 'processed_at' => 'datetime', 'metadata' => 'array'];
    }
}
