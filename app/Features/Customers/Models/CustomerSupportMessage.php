<?php

namespace App\Features\Customers\Models;

use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerSupportMessage extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id'];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(CustomerSupportRequest::class, 'customer_support_request_id');
    }

    protected function casts(): array
    {
        return ['attachment' => 'array', 'sent_at' => 'datetime'];
    }
}
