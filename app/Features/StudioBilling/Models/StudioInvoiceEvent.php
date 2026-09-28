<?php

namespace App\Features\StudioBilling\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['studio_invoice_id', 'user_id', 'action', 'details', 'created_at'])]
class StudioInvoiceEvent extends Model
{
    public $timestamps = false;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return ['details' => 'array', 'created_at' => 'datetime'];
    }
}
