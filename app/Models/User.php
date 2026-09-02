<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Features\Concerts\Models\ConcertAccess;
use App\Features\Concerts\Models\ConcertAccessGrant;
use App\Features\Crew\Models\CrewProfile;
use App\Features\Customers\Models\CustomerProfile;
use App\Features\Customers\Support\UserType;
use App\Features\Orders\Models\Order;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'is_active', 'type', 'last_login_at', 'last_seen_at', 'invitation_sent_at', 'onboarding_completed_at'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    public function homeRouteName(): string
    {
        return $this->type === UserType::Crew->value ? 'crew.availability.index' : 'admin.dashboard';
    }

    public function customerProfile(): HasOne
    {
        return $this->hasOne(CustomerProfile::class);
    }

    public function crewProfile(): HasOne
    {
        return $this->hasOne(CrewProfile::class);
    }

    public function concertAccessGrants(): HasMany
    {
        return $this->hasMany(ConcertAccessGrant::class);
    }

    public function concertAccesses(): HasMany
    {
        return $this->hasMany(ConcertAccess::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'invitation_sent_at' => 'datetime',
            'onboarding_completed_at' => 'datetime',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
