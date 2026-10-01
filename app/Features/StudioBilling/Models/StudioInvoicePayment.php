<?php

namespace App\Features\StudioBilling\Models;

use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['uuid', 'idempotency_key', 'studio_invoice_id', 'user_id', 'provider', 'status', 'is_test', 'amount_cents', 'currency', 'provider_transaction_id', 'reference', 'message', 'paid_at', 'reversed_at'])]
class StudioInvoicePayment extends Model
{
    use HasPublicUuid;

    protected function casts(): array
    {
        return ['receipt' => 'array', 'is_test' => 'boolean', 'amount_cents' => 'integer', 'paid_at' => 'datetime', 'reversed_at' => 'datetime'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(StudioInvoice::class, 'studio_invoice_id');
    }
}
