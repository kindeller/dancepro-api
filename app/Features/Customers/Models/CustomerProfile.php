<?php

namespace App\Features\Customers\Models;

use App\Models\User;
use Database\Factories\CustomerProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['user_id', 'preferred_name', 'phone', 'registration_source', 'terms_accepted_at', 'privacy_accepted_at', 'marketing_consent_at', 'preferences'])]
class CustomerProfile extends Model
{
    /** @use HasFactory<CustomerProfileFactory> */
    use HasFactory, SoftDeletes;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'terms_accepted_at' => 'datetime', 'privacy_accepted_at' => 'datetime',
            'marketing_consent_at' => 'datetime', 'preferences' => 'array',
        ];
    }

    protected static function newFactory(): CustomerProfileFactory
    {
        return CustomerProfileFactory::new();
    }
}
