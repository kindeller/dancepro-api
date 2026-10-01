<?php

namespace App\Models;

use App\Features\Concerts\Models\ConcertAccess;
use App\Features\Concerts\Models\ConcertAccessGrant;
use App\Features\Crew\Models\CrewProfile;
use App\Features\Customers\Models\CustomerProfile;
use App\Features\Customers\Support\UserType;
use App\Features\Orders\Models\Order;
use App\Features\Payments\Models\CustomerCreditTransaction;
use App\Features\Studios\Models\StudioAccountAccess;
use App\Shared\Models\HasPublicUuid;
use App\Shared\Support\AccountName;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'first_name', 'last_name', 'email', 'email_verified_at', 'password', 'is_active', 'is_admin', 'type', 'account_status', 'paused_at', 'cancelled_at', 'anonymized_at', 'account_status_reason', 'account_status_changed_by_user_id', 'merged_into_user_id', 'last_login_at', 'last_seen_at', 'invitation_sent_at', 'onboarding_completed_at'])]
#[Hidden(['password', 'pending_email', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasPublicUuid, Notifiable, SoftDeletes;

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if ($user->exists && $user->isDirty('email')) {
                $user->pending_email = null;
                $user->pending_email_requested_at = null;
            }
            if ($user->isDirty('first_name') || $user->isDirty('last_name')) {
                $user->first_name = trim((string) $user->first_name);
                $user->last_name = trim((string) $user->last_name) ?: null;
                $user->name = trim($user->first_name.' '.($user->last_name ?? ''));
            } elseif ($user->isDirty('name')) {
                foreach (AccountName::split((string) $user->name) as $field => $value) {
                    $user->{$field} = $value;
                }
            }
        });
    }

    public function emailGreetingName(): string
    {
        return $this->first_name ?: AccountName::split((string) $this->name)['first_name'];
    }

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        // Older account links remain valid; new links use the same UUID format
        // as studios, events and conversations. Database relations still use id.
        return parent::resolveRouteBindingQuery($query, $value, $field ?? (ctype_digit((string) $value) ? 'id' : 'uuid'));
    }

    public function homeRouteName(): string
    {
        return $this->canAccessAdmin() ? 'admin.dashboard' : ($this->type !== UserType::Customer->value && $this->canAccessCrew() ? 'crew.availability.index' : 'customer.media.index');
    }

    public function canAccessAdmin(): bool
    {
        return $this->is_active && $this->is_admin;
    }

    public function canAccessCustomer(): bool
    {
        return $this->is_active && (in_array($this->type, [UserType::Customer->value, UserType::Crew->value, UserType::Staff->value], true) || $this->canAccessAdmin());
    }

    public function canAccessStudioHub(): bool
    {
        if (! $this->canAccessCustomer()) {
            return false;
        }

        return StudioAccountAccess::query()->where('status', 'active')
            ->where(fn ($query) => $query->where('user_id', $this->id)
                ->orWhereRaw('lower(email) = ?', [mb_strtolower($this->email)]))
            ->exists();
    }

    public function canAccessCrew(): bool
    {
        return $this->is_active
            && $this->crewProfile !== null;
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

    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CustomerCreditTransaction::class);
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
            'pending_email_requested_at' => 'datetime',
            'invitation_sent_at' => 'datetime',
            'onboarding_completed_at' => 'datetime',
            'is_active' => 'boolean',
            'paused_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'anonymized_at' => 'datetime',
            'is_admin' => 'boolean',
            'last_login_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
