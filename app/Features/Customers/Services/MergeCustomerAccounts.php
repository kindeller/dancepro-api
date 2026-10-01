<?php

namespace App\Features\Customers\Services;

use App\Features\Customers\Models\CustomerAccountActivity;
use App\Features\Families\Models\CustomerFamilyMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MergeCustomerAccounts
{
    public function execute(User $source, User $target, User $actor, string $confirmationEmail, string $reason): void
    {
        if ($source->id === $target->id || $source->type !== 'customer' || $target->type !== 'customer' || $source->merged_into_user_id) {
            abort(409);
        }
        if (mb_strtolower($confirmationEmail) !== mb_strtolower($target->email)) {
            throw ValidationException::withMessages(['confirmation_email' => 'Enter the primary account email address to confirm the merge.']);
        }
        DB::transaction(function () use ($source, $target, $actor, $reason, $confirmationEmail): void {
            $locked = User::whereKey([$source->id, $target->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $source = $locked->get($source->id);
            $target = $locked->get($target->id);
            abort_if(! $source || ! $target || $source->merged_into_user_id || $target->merged_into_user_id || $source->anonymized_at || $target->anonymized_at || ! $target->is_active, 409);
            if (mb_strtolower($confirmationEmail) !== mb_strtolower($target->email)) {
                throw ValidationException::withMessages(['confirmation_email' => 'The primary account changed. Review it again.']);
            }
            if ($source->is_admin || $target->is_admin || $source->crewProfile()->exists() || $target->crewProfile()->exists()) {
                throw ValidationException::withMessages(['confirmation_email' => 'Accounts with crew or Admin access cannot be merged through customer account management.']);
            }
            if (CustomerFamilyMember::whereIn('user_id', [$source->id, $target->id])->exists()) {
                throw ValidationException::withMessages(['confirmation_email' => 'Resolve family memberships before merging accounts so shared permissions are not transferred to another person.']);
            }
            $summary = ['source_name' => $source->name, 'source_email' => $source->email, 'orders' => DB::table('orders')->where('user_id', $source->id)->count(), 'credits' => DB::table('customer_credit_transactions')->where('user_id', $source->id)->count(), 'downloads' => DB::table('order_media_asset_downloads')->where('user_id', $source->id)->count()];
            $this->mergeUnique($source, $target, 'customer_dancers', ['name']);
            $this->mergeUnique($source, $target, 'customer_studios', ['studio_id']);
            $this->mergeUnique($source, $target, 'customer_competition_registrations', ['scheduling_event_id']);
            $this->mergeUnique($source, $target, 'customer_media_selections', ['scheduling_event_id', 'selection_key'], ['is_favourite', 'in_cart']);
            $this->mergeUnique($source, $target, 'customer_concert_photo_selections', ['media_asset_id'], ['is_favourite', 'in_cart']);
            foreach (['orders', 'customer_credit_transactions', 'customer_media_notifications', 'customer_support_requests', 'customer_inbox_notifications', 'order_media_asset_downloads', 'customer_account_emails', 'customer_account_activities', 'customer_account_notes', 'customer_risk_events', 'customer_privacy_requests', 'customer_consent_histories', 'customer_login_events', 'customer_engagement_events', 'concert_access_grants', 'concert_accesses', 'payment_attempts', 'guest_gallery_registrations', 'guest_studio_subscriptions', 'customer_help_searches'] as $table) {
                DB::table($table)->where('user_id', $source->id)->update(['user_id' => $target->id]);
            }
            DB::table('customer_account_merges')->insert(['uuid' => (string) Str::uuid(), 'source_user_id' => $source->id, 'target_user_id' => $target->id, 'merged_by_user_id' => $actor->id, 'reason' => $reason, 'summary' => json_encode($summary, JSON_THROW_ON_ERROR), 'merged_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            CustomerAccountActivity::create(['user_id' => $target->id, 'actor_user_id' => $actor->id, 'type' => 'accounts_merged', 'summary' => 'Account #'.$source->id.' merged into this account.', 'metadata' => $summary + ['reason' => $reason, 'source_user_id' => $source->id]]);
            $source->tokens()->delete();
            DB::table('sessions')->where('user_id', $source->id)->delete();
            DB::table('notifications')->where('notifiable_type', $source->getMorphClass())->where('notifiable_id', $source->id)->update(['notifiable_id' => $target->id]);
            $this->mergeUnique($source, $target, 'customer_media_archives', ['scope', 'kind', 'event_uuid', 'fingerprint']);
            $this->mergeUnique($source, $target, 'customer_help_article_feedback', ['customer_help_article_id']);
            $this->mergeProfile($source, $target);
            $source->update(['name' => 'Merged account '.$source->id, 'email' => 'merged-'.$source->id.'-'.Str::lower(Str::random(12)).'@deleted.invalid', 'email_verified_at' => null, 'is_active' => false, 'account_status' => 'cancelled', 'merged_into_user_id' => $target->id, 'cancelled_at' => now(), 'account_status_reason' => 'Merged into account #'.$target->id]);
        });
    }

    private function mergeProfile(User $source, User $target): void
    {
        $sourceProfile = DB::table('customer_profiles')->where('user_id', $source->id)->first();
        $targetProfile = DB::table('customer_profiles')->where('user_id', $target->id)->first();
        if (! $sourceProfile) {
            return;
        }
        if (! $targetProfile) {
            DB::table('customer_profiles')->where('id', $sourceProfile->id)->update(['user_id' => $target->id]);

            return;
        }
        // Keep the primary account's preferences. Record conflicting contact details for staff review.
        $updates = [];
        $conflicts = [];
        foreach (['phone', 'preferred_name'] as $field) {
            if (! $targetProfile->$field && ! $sourceProfile->deleted_at) {
                $updates[$field] = $sourceProfile->$field;
            } elseif ($sourceProfile->$field && $sourceProfile->$field !== $targetProfile->$field) {
                $conflicts[$field] = $sourceProfile->$field;
            }
        }
        if ($updates) {
            DB::table('customer_profiles')->where('id', $targetProfile->id)->update($updates);
        }
        if ($conflicts) {
            CustomerAccountActivity::create(['user_id' => $target->id, 'type' => 'merge_contact_review', 'summary' => 'Secondary contact details retained for review; primary preferences unchanged.', 'metadata' => $conflicts]);
        }
        DB::table('customer_profiles')->where('id', $sourceProfile->id)->update(['preferred_name' => null, 'phone' => null, 'preferences' => null, 'marketing_consent_at' => null, 'deleted_at' => now()]);
    }

    private function mergeUnique(User $source, User $target, string $table, array $identity, array $booleans = []): void
    {
        foreach (DB::table($table)->where('user_id', $source->id)->get() as $row) {
            $key = ['user_id' => $target->id];
            foreach ($identity as $column) {
                $key[$column] = $row->$column;
            }
            $existing = DB::table($table)->where($key)->first();
            if ($existing) {
                $updates = [];
                foreach ($booleans as $column) {
                    $updates[$column] = (bool) $existing->$column || (bool) $row->$column;
                }
                if (property_exists($row, 'access_verified_at')) {
                    $updates['access_verified_at'] = $existing->access_verified_at ?: $row->access_verified_at;
                }
                if ($table === 'customer_dancers') {
                    $updates['programme_names'] = json_encode(array_values(array_unique(array_merge(json_decode($existing->programme_names ?? '[]', true) ?: [], json_decode($row->programme_names ?? '[]', true) ?: []))));
                }
                if ($updates) {
                    DB::table($table)->where('id', $existing->id)->update($updates);
                }
            } else {
                DB::table($table)->where('id', $row->id)->update(['user_id' => $target->id]);
            }
        }
        DB::table($table)->where('user_id', $source->id)->delete();
    }
}
