<?php

namespace App\Features\Customers\Models;

use App\Features\Orders\Models\Order;
use App\Features\Orders\Models\OrderMediaEntitlement;
use App\Models\User;
use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerSupportRequest extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id'];

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

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CustomerSupportMessage::class)->orderBy('sent_at')->orderBy('id');
    }

    public function accountEmail(): BelongsTo
    {
        return $this->belongsTo(CustomerAccountEmail::class, 'customer_account_email_id');
    }

    protected function casts(): array
    {
        return ['context' => 'array', 'requested_at' => 'datetime', 'decided_at' => 'datetime', 'customer_read_at' => 'datetime', 'latest_staff_message_at' => 'datetime', 'last_activity_at' => 'datetime'];
    }
}
