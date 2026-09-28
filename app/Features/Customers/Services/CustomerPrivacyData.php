<?php

namespace App\Features\Customers\Services;

use App\Features\Customers\Models\CustomerSupportMessage;
use App\Features\Customers\Models\GuestGalleryAccess;
use App\Features\Customers\Models\GuestGalleryRegistration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CustomerPrivacyData
{
    private const ACCOUNT_TABLES = [
        'customer_profiles', 'customer_dancers', 'customer_studios', 'customer_competition_registrations',
        'customer_media_selections', 'customer_concert_photo_selections', 'customer_account_emails',
        'customer_inbox_notifications', 'customer_media_notifications', 'customer_support_requests', 'customer_account_activities',
        'customer_account_notes', 'customer_risk_events', 'customer_privacy_requests',
        'customer_consent_histories', 'customer_login_events', 'customer_engagement_events',
        'concert_access_grants', 'concert_accesses', 'order_media_asset_downloads',
        'customer_credit_transactions', 'payment_attempts', 'customer_media_archives',
        'customer_help_searches', 'customer_help_article_feedback',
    ];

    public function export(User $customer): array
    {
        $data = ['scope' => 'Customer account records; original media, infrastructure logs and backups are not included.'];
        foreach (self::ACCOUNT_TABLES as $table) {
            $data[$table] = DB::table($table)->where('user_id', $customer->id)->get()
                ->map(fn ($row) => collect((array) $row)->except(['session_identifier', 'storage_key', 'storage_disk', 'fingerprint', 'deduplication_key'])->all())->all();
        }
        $data['customer_support_messages'] = CustomerSupportMessage::whereHas('conversation', fn ($query) => $query->where('user_id', $customer->id))->get()->map(fn ($message) => ['uuid' => $message->uuid, 'direction' => $message->direction, 'body' => $message->body, 'sent_at' => $message->sent_at, 'attachment_name' => data_get($message->attachment, 'name')])->all();
        $hash = hash('sha256', mb_strtolower(trim($customer->email)));
        $accesses = GuestGalleryAccess::where('email_hash', $hash)->get();
        $data['guest_gallery_accesses'] = $accesses->map(fn ($access) => $access->makeVisible('email')->toArray())->all();
        $data['guest_gallery_selections'] = DB::table('guest_gallery_selections')->whereIn('guest_gallery_access_id', $accesses->pluck('id'))->get()->toArray();
        $data['gallery_registrations'] = GuestGalleryRegistration::where('user_id', $customer->id)->orWhere('email_hash', $hash)->get()
            ->map(fn ($registration) => $registration->makeVisible('email')->toArray())->all();
        $data['in_app_notifications'] = DB::table('notifications')->where('notifiable_type', $customer->getMorphClass())->where('notifiable_id', $customer->id)->get()->toArray();

        return $data;
    }

    /** Redact account/contact records, preserving order amounts and media identities. */
    public function redact(User $customer, string $anonymousEmail): void
    {
        DB::table('customer_inbox_notifications')->where('user_id', $customer->id)->update(['subject' => '[redacted]', 'body' => '[redacted]', 'action_url' => null]);
        $messages = CustomerSupportMessage::whereHas('conversation', fn ($query) => $query->where('user_id', $customer->id))->get();
        foreach ($messages as $message) {
            if ($path = data_get($message->attachment, 'path')) {
                Storage::disk('local')->delete($path);
            }
            $message->update(['body' => '[redacted]', 'attachment' => null]);
        }
        DB::table('customer_profiles')->where('user_id', $customer->id)->update(['preferred_name' => null, 'phone' => null, 'preferences' => null, 'marketing_consent_at' => null]);
        DB::table('customer_media_notifications')->where('user_id', $customer->id)->update(['recipient_email' => $anonymousEmail, 'subject' => '[redacted]', 'metadata' => null]);
        DB::table('customer_risk_events')->where('user_id', $customer->id)->update(['details' => '[redacted]', 'metadata' => null]);
        DB::table('customer_privacy_requests')->where('user_id', $customer->id)->update(['reason' => '[redacted]', 'metadata' => null]);
        DB::table('customer_credit_transactions')->where('user_id', $customer->id)->update(['note' => '[redacted]']);
        DB::table('concert_access_grants')->where('user_id', $customer->id)->update(['email' => $anonymousEmail, 'revoke_reason' => null, 'metadata' => null]);
        DB::table('concert_accesses')->where('user_id', $customer->id)->update(['student_name' => null, 'ip_address' => null, 'user_agent' => null, 'referrer' => null, 'session_identifier' => null, 'failure_reason' => null]);
        DB::table('customer_account_activities')->where('user_id', $customer->id)->update(['summary' => '[redacted]', 'metadata' => null]);
        DB::table('customer_engagement_events')->where('user_id', $customer->id)->update(['metadata' => null]);
        DB::table('customer_account_merges')->where('source_user_id', $customer->id)->orWhere('target_user_id', $customer->id)->update(['reason' => '[redacted]', 'summary' => '{}']);
        DB::table('sessions')->where('user_id', $customer->id)->delete();
        DB::table('notifications')->where('notifiable_type', $customer->getMorphClass())->where('notifiable_id', $customer->id)->delete();
        DB::table('customer_help_searches')->where('user_id', $customer->id)->delete();
        $hash = hash('sha256', mb_strtolower(trim($customer->email)));
        $accesses = GuestGalleryAccess::where('email_hash', $hash)->get();
        foreach ($accesses as $access) {
            $access->update(['email' => $anonymousEmail, 'email_hash' => hash('sha256', $anonymousEmail), 'expires_at' => now()]);
        }
        GuestGalleryRegistration::where('user_id', $customer->id)->orWhere('email_hash', $hash)->get()->each(
            fn ($registration) => $registration->update(['email' => $anonymousEmail, 'email_hash' => hash('sha256', $anonymousEmail), 'unsubscribed_at' => now()])
        );
    }
}
