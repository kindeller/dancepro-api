<?php

namespace App\Features\Payments\Models;

use App\Features\Orders\Models\Order;
use App\Features\Orders\Models\OrderMediaEntitlement;
use App\Models\User;
use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['uuid', 'user_id', 'order_id', 'order_media_entitlement_id', 'type', 'deduplication_key', 'recipient_email', 'subject', 'status', 'queued_at', 'sent_at', 'failed_at', 'metadata'])]
class CustomerMediaNotification extends Model
{
    use HasPublicUuid;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function entitlement(): BelongsTo
    {
        return $this->belongsTo(OrderMediaEntitlement::class, 'order_media_entitlement_id');
    }

    protected function casts(): array
    {
        return ['queued_at' => 'datetime', 'sent_at' => 'datetime', 'failed_at' => 'datetime', 'metadata' => 'array'];
    }
}
