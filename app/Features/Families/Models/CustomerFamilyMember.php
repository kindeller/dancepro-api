<?php

namespace App\Features\Families\Models;

use App\Features\Customers\Models\CustomerDancer;
use App\Models\User;
use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerFamilyMember extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id'];

    protected $hidden = ['invitation_hash'];

    protected function casts(): array
    {
        return ['can_view_media' => 'boolean', 'can_purchase' => 'boolean', 'is_dancer' => 'boolean',
            'invited_at' => 'datetime', 'expires_at' => 'datetime', 'accepted_at' => 'datetime'];
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(CustomerFamily::class, 'family_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dancer(): BelongsTo
    {
        return $this->belongsTo(CustomerDancer::class, 'customer_dancer_id');
    }

    public function displayName(): string
    {
        return $this->user?->name ?? trim($this->first_name.' '.$this->last_name);
    }
}
