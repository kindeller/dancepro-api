<?php

namespace App\Features\Customers\Services;

use App\Features\Customers\Notifications\AccountEmailChange;
use App\Features\Families\Models\CustomerFamily;
use App\Features\Families\Models\CustomerFamilyMember;
use App\Features\Studios\Models\StudioAccountAccess;
use App\Features\Studios\Models\StudioContact;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerAccountEmailChange
{
    public function request(User $user, string $email): void
    {
        if ($email === $user->email) {
            return;
        }
        $user->forceFill(['pending_email' => $email, 'pending_email_requested_at' => now()])->save();
        $url = URL::temporarySignedRoute('customer.profile.email.confirm', now()->addHour(), [
            'account' => $user->uuid, 'hash' => $this->hash($user),
        ]);
        DB::afterCommit(fn () => Notification::route('mail', $email)->notify(new AccountEmailChange($user->emailGreetingName(), $email, $url)));
    }

    public function confirm(User $user, string $account, string $hash, string $sessionId): void
    {
        DB::transaction(function () use ($user, $account, $hash, $sessionId): void {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($user->canAccessCustomer() && $user->uuid === $account && $user->pending_email
                && hash_equals($this->hash($user), $hash), 403, 'This email change is no longer available.');
            $email = $user->pending_email;
            if (User::withTrashed()->whereRaw('lower(email) = ?', [$email])->whereKeyNot($user->id)->exists()) {
                throw ValidationException::withMessages(['email' => 'That email address already belongs to another account.']);
            }
            $accesses = StudioAccountAccess::where('user_id', $user->id)->lockForUpdate()->get();
            foreach ($accesses as $access) {
                if (StudioAccountAccess::where('studio_id', $access->studio_id)->where('email', $email)->whereKeyNot($access->id)->exists()) {
                    throw ValidationException::withMessages(['email' => 'That address already has a separate Studio Hub invitation. Contact DancePro to combine the access safely.']);
                }
                $access->update(['email' => $email]);
            }
            $familyMember = CustomerFamilyMember::where('user_id', $user->id)->first();
            if ($familyMember) {
                CustomerFamily::whereKey($familyMember->family_id)->lockForUpdate()->firstOrFail();
                if ($familyMember->family->members()->where('email', $email)->whereKeyNot($familyMember->id)->exists()) {
                    throw ValidationException::withMessages(['email' => 'That email is already used by another family profile or invitation. Ask your account manager to update it first.']);
                }
                $familyMember->update(['email' => $email]);
            }
            $oldEmail = $user->email;
            foreach (StudioContact::where('user_id', $user->id)->get() as $contact) {
                $contact->update(['emails' => array_values(array_unique(array_map(
                    fn ($address) => mb_strtolower($address) === mb_strtolower($oldEmail) ? $email : $address, $contact->emailAddresses()
                )))]);
            }
            $user->forceFill(['email' => $email, 'email_verified_at' => now(), 'pending_email' => null,
                'pending_email_requested_at' => null, 'remember_token' => Str::random(60)])->save();
            $user->tokens()->delete();
            if (config('session.driver') === 'database') {
                DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', $sessionId)->delete();
            }
            DB::afterCommit(fn () => Notification::route('mail', $oldEmail)->notify(new AccountEmailChange($user->emailGreetingName(), $email)));
        });
    }

    private function hash(User $user): string
    {
        return hash('sha256', $user->pending_email.'|'.$user->pending_email_requested_at?->format('Y-m-d H:i:s'));
    }
}
