<?php

namespace App\Features\Families\Services;

use App\Features\Customers\Models\CustomerDancer;
use App\Features\Customers\Support\CustomerDancerNameMatcher;
use App\Features\Families\Models\CustomerFamily;
use App\Features\Families\Models\CustomerFamilyMember;
use App\Features\Families\Notifications\FamilyInvitation;
use App\Features\Studios\Models\Studio;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerFamilies
{
    public function __construct(private readonly FamilyAccess $access) {}

    public function page(User $user): array
    {
        $family = $this->ensure($user);
        $family->load('manager', 'members.user', 'members.dancer');

        return ['family' => $family, 'familyManager' => $family->manager_user_id === $user->id];
    }

    public function ensure(User $user): CustomerFamily
    {
        return DB::transaction(function () use ($user): CustomerFamily {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($member = $this->access->membership($user)) {
                return $member->family;
            }
            $family = CustomerFamily::firstOrCreate(['manager_user_id' => $user->id]);
            $manager = $family->members()->create(['user_id' => $user->id, 'first_name' => $user->first_name ?: $user->name,
                'last_name' => $user->last_name, 'email' => strtolower($user->email), 'can_view_media' => true,
                'can_purchase' => true, 'status' => 'active', 'accepted_at' => now()]);
            foreach (CustomerDancer::where('user_id', $user->id)->orderBy('id')->get() as $dancer) {
                if ($dancer->relationship === 'self' && ! $manager->customer_dancer_id) {
                    $manager->update(['customer_dancer_id' => $dancer->id, 'is_dancer' => true]);

                    continue;
                }
                $parts = preg_split('/\s+/', trim($dancer->name), 2);
                $family->members()->create(['first_name' => $parts[0], 'last_name' => $parts[1] ?? null,
                    'customer_dancer_id' => $dancer->id, 'is_dancer' => true, 'status' => 'dancer']);
            }

            return $family;
        });
    }

    public function change(User $user, array $input): string
    {
        $family = $this->ensure($user);

        return DB::transaction(function () use ($user, $input, $family): string {
            $family = CustomerFamily::whereKey($family->id)->lockForUpdate()->firstOrFail();
            if ($input['action'] === 'leave') {
                abort_if($family->manager_user_id === $user->id, 403);
                $family->members()->where('user_id', $user->id)->firstOrFail()->delete();

                return 'You have left the family. Your own purchases remain in your account.';
            }
            abort_unless($family->manager_user_id === $user->id, 403);
            $member = ! empty($input['member_uuid']) ? $family->members()->where('uuid', $input['member_uuid'])->firstOrFail() : null;
            if ($input['action'] === 'manager') {
                $member = $family->members()->where('user_id', $user->id)->firstOrFail();
                $member->fill(['first_name' => $user->first_name ?: $user->name, 'last_name' => $user->last_name, 'is_dancer' => (bool) ($input['is_dancer'] ?? false)]);
                $this->syncDancer($family, $member, $input['programme_names'] ?? null);
                $member->save();

                return 'Your dancer preference has been saved.';
            }
            abort_if($member && $member->user_id === $user->id, 403);
            if ($input['action'] === 'save') {
                $member = $this->save($family, $member, $input);
                if ($member->email && ($member->status === 'draft' || ($member->status === 'pending' && $member->expires_at?->isPast()))) {
                    $this->invite($family, $member);

                    return 'Family member saved and invitation sent. They can accept using their verified email address.';
                }

                return 'Family member saved.';
            }
            abort_unless($member, 404);
            if ($input['action'] === 'remove') {
                // Only the family link is removed. Accounts, purchases and original dancer records survive.
                $member->dancer?->delete();
                $member->delete();

                return 'Family member removed. Their own account and purchases are unchanged.';
            }
            if ($input['action'] === 'cancel') {
                abort_if($member->status === 'active', 422, 'Remove this member to end their accepted access.');
                $member->update(['invitation_hash' => null, 'expires_at' => null, 'status' => 'draft']);

                return 'Invitation cancelled.';
            }
            $this->invite($family, $member);

            return 'Family invitation sent. It expires in 14 days; previous invitation links no longer work.';
        });
    }

    private function save(CustomerFamily $family, ?CustomerFamilyMember $member, array $input): CustomerFamilyMember
    {
        $view = (bool) ($input['can_view_media'] ?? false);
        $purchase = (bool) ($input['can_purchase'] ?? false);
        $dancer = (bool) ($input['is_dancer'] ?? false);
        if (! $view && ! $purchase && ! $dancer) {
            throw ValidationException::withMessages(['is_dancer' => 'Select Dancer or at least one account permission.']);
        }
        $email = ($view || $purchase) ? strtolower(trim($input['email'] ?? '')) : null;
        if (($view || $purchase) && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['email' => 'Enter an email address for account access.']);
        }
        if ($email && (strtolower($family->manager->email) === $email || $family->members()
            ->when($member, fn ($q) => $q->where('id', '!=', $member->id))
            ->where(fn ($q) => $q->whereRaw('LOWER(email) = ?', [$email])->orWhereHas('user', fn ($q) => $q->whereRaw('LOWER(email) = ?', [$email])))->exists())) {
            throw ValidationException::withMessages(['email' => 'Each family member needs a different email address, including the account manager.']);
        }
        if ($member?->user_id && $email && strtolower($member->user->email) !== $email) {
            throw ValidationException::withMessages(['email' => 'An accepted member changes their email in their own My details page. Remove this member before inviting a different person.']);
        }
        $member ??= new CustomerFamilyMember(['family_id' => $family->id]);
        $changesInvite = $member->email !== $email || $member->can_view_media !== $view || $member->can_purchase !== $purchase;
        // Accepted accounts own their names; the family remains linked by user ID.
        $member->fill(['first_name' => $member->user_id ? $member->user->first_name : $input['first_name'],
            'last_name' => $member->user_id ? $member->user->last_name : ($input['last_name'] ?? null), 'email' => $email,
            'can_view_media' => $view, 'can_purchase' => $purchase, 'is_dancer' => $dancer]);
        if (! $email) {
            $member->fill(['user_id' => null, 'status' => 'dancer', 'accepted_at' => null, 'invitation_hash' => null, 'expires_at' => null]);
        } elseif ($member->status !== 'active' && $changesInvite) {
            $member->fill(['status' => 'draft', 'invitation_hash' => null, 'expires_at' => null]);
        }
        $this->syncDancer($family, $member, $input['programme_names'] ?? null);
        $member->save();

        return $member;
    }

    private function syncDancer(CustomerFamily $family, CustomerFamilyMember $member, ?array $aliases): void
    {
        if (! $member->is_dancer) {
            return;
        }
        $name = trim($member->first_name.' '.$member->last_name);
        $matcher = app(CustomerDancerNameMatcher::class);
        $studioNames = Studio::where('status', 'active')->pluck('name')->map(fn ($name) => $matcher->canonical($name));
        if (collect([$name, ...($aliases ?? [])])->contains(fn ($name) => $studioNames->contains($matcher->canonical($name)))) {
            throw ValidationException::withMessages(['first_name' => 'Use an individual dancer name. Add studios in My studio.']);
        }
        $dancer = $member->dancer ?? CustomerDancer::firstOrNew(['user_id' => $family->manager_user_id, 'name' => $name]);
        if ($dancer->exists && CustomerFamilyMember::where('customer_dancer_id', $dancer->id)->when($member->exists, fn ($q) => $q->where('id', '!=', $member->id))->exists()) {
            throw ValidationException::withMessages(['first_name' => 'This dancer is already in your family. Edit their existing profile.']);
        }
        $dancer->fill(['name' => $name, 'relationship' => $member->user_id === $family->manager_user_id ? 'self' : ($dancer->relationship ?? 'child'),
            'programme_names' => $aliases ?? $dancer->programme_names ?? []])->save();
        $member->customer_dancer_id = $dancer->id;
    }

    private function invite(CustomerFamily $family, CustomerFamilyMember $member): void
    {
        if ($member->status === 'active' || ! $member->email || (! $member->can_view_media && ! $member->can_purchase)) {
            throw ValidationException::withMessages(['family' => 'Only members awaiting account access can be invited. Dancer-only profiles do not need an invitation.']);
        }
        if ($member->invited_at?->gt(now()->subMinute())) {
            throw ValidationException::withMessages(['family' => 'An invitation was just sent. Please wait a minute before resending.']);
        }
        $token = Str::random(64);
        $member->update(['invitation_hash' => hash('sha256', $token), 'invited_at' => now(), 'expires_at' => now()->addDays(14), 'status' => 'pending']);
        $url = route('customer.family.invitation', ['member' => $member, 'token' => $token]);
        DB::afterCommit(fn () => Notification::route('mail', $member->email)->notify(new FamilyInvitation($family->manager->name, $member->first_name, $url, $member->can_view_media, $member->can_purchase)));
    }

    public function syncLegacyDancer(User $user, CustomerDancer $dancer): void
    {
        $family = CustomerFamily::where('manager_user_id', $user->id)->first();
        if (! $family) {
            return;
        }
        $parts = preg_split('/\s+/', trim($dancer->name), 2);
        $member = $family->members()->firstOrNew(['customer_dancer_id' => $dancer->id]);
        $member->fill(['first_name' => $parts[0], 'last_name' => $parts[1] ?? null, 'is_dancer' => true]);
        if (! $member->exists) {
            $member->status = 'dancer';
        }
        $member->save();
    }

    public function removeLegacyDancer(User $user, CustomerDancer $dancer): void
    {
        $member = CustomerFamilyMember::where('customer_dancer_id', $dancer->id)->first();
        if ($member) {
            if ($member->user_id || $member->email) {
                $member->update(['is_dancer' => false, 'customer_dancer_id' => null]);
            } else {
                $member->delete();
            }
        }
        $dancer->delete();
    }

    public function invitation(CustomerFamilyMember $member, string $token): CustomerFamilyMember
    {
        abort_unless($member->status === 'pending' && $member->invitation_hash
            && hash_equals($member->invitation_hash, hash('sha256', $token)) && $member->expires_at?->isFuture(), 410, 'This invitation has expired or was cancelled. Ask your family account manager for a new invitation.');

        return $member->load('family.manager');
    }

    public function accept(User $user, CustomerFamilyMember $member, string $token): void
    {
        abort_unless($user->canAccessCustomer() && $user->hasVerifiedEmail(), 403);
        DB::transaction(function () use ($user, $member, $token): void {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            CustomerFamily::whereKey($member->family_id)->lockForUpdate()->firstOrFail();
            $member = $this->invitation(CustomerFamilyMember::whereKey($member->id)->lockForUpdate()->firstOrFail(), $token);
            if (strtolower($user->email) !== strtolower($member->email)) {
                throw ValidationException::withMessages(['family' => 'Sign in with the verified email address that received this invitation.']);
            }
            if ($existing = $this->access->membership($user)) {
                // An unused personal manager profile may be replaced; a populated family may not.
                $emptyOwn = $existing->family->manager_user_id === $user->id && $existing->family->members()->count() === 1 && ! $existing->is_dancer;
                if (! $emptyOwn) {
                    throw ValidationException::withMessages(['family' => 'You already belong to a family. Leave it first, or contact DancePro if you manage family members.']);
                }
                $existing->family->delete();
            }
            $member->update(['user_id' => $user->id, 'first_name' => $user->first_name, 'last_name' => $user->last_name,
                'status' => 'active', 'accepted_at' => now(), 'invitation_hash' => null, 'expires_at' => null]);
        });
    }
}
