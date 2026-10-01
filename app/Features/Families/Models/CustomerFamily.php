<?php

namespace App\Features\Families\Models;

use App\Models\User;
use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerFamily extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id'];

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(CustomerFamilyMember::class, 'family_id');
    }
}
