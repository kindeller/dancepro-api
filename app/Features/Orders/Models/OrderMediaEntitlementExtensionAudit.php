<?php

namespace App\Features\Orders\Models;

use App\Features\Customers\Models\CustomerSupportRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderMediaEntitlementExtensionAudit extends Model
{
    protected $guarded = ['id'];

    public function entitlement(): BelongsTo
    {
        return $this->belongsTo(OrderMediaEntitlement::class, 'order_media_entitlement_id');
    }

    public function supportRequest(): BelongsTo
    {
        return $this->belongsTo(CustomerSupportRequest::class, 'customer_support_request_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    protected function casts(): array
    {
        return ['previous_expires_at' => 'datetime', 'new_expires_at' => 'datetime', 'extension_days' => 'integer'];
    }
}
