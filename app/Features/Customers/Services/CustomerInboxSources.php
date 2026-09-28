<?php

namespace App\Features\Customers\Services;

use App\Features\Customers\Models\CustomerAccountEmail;
use App\Features\Customers\Models\CustomerInboxNotification;
use App\Features\Customers\Models\CustomerStudio;
use App\Features\Customers\Models\CustomerSupportRequest;
use App\Features\Media\Models\ConcertPhotoReleaseGroup;
use App\Features\Payments\Models\CustomerMediaNotification;
use App\Features\Tracking\Models\ConcertTrackerEmailDelivery;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Schema;

class CustomerInboxSources
{
    public function sync(Model $source): void
    {
        if (! Schema::hasTable('customer_inbox_notifications')) {
            return;
        }
        if ($source instanceof CustomerMediaNotification) {
            if (str_starts_with($source->type, 'test_')) {
                return;
            }
            $this->record($source->user_id, 'media:'.$source->id, $source->subject,
                data_get($source->metadata, 'body', 'An update about your DancePro media is available. Open your order for details.'),
                data_get($source->metadata, 'action_url', $source->order ? route('customer.checkout.show', $source->order) : route('customer.media.index')),
                data_get($source->metadata, 'action_label', 'View details'), $source->status, $source->sent_at, $source->queued_at);
        } elseif ($source instanceof CustomerAccountEmail) {
            if (! $this->availableAccount($source->user_id)) {
                return;
            }
            if (data_get($source->metadata, 'kind')) {
                $this->record($source->user_id, 'account:'.$source->id, $source->subject, $source->body,
                    route('customer.media.index'), 'Open My Media', $source->status, $source->sent_at, $source->queued_at);
            } else {
                CustomerSupportRequest::firstOrCreate(['customer_account_email_id' => $source->id], [
                    'user_id' => $source->user_id, 'type' => 'general', 'subject' => $source->subject,
                    'message' => $source->body, 'status' => 'resolved', 'requested_at' => $source->queued_at ?? $source->created_at,
                    'decided_by_user_id' => $source->sent_by_user_id,
                ]);
            }
        } elseif ($source instanceof DatabaseNotification) {
            if ($source->notifiable_type !== (new User)->getMorphClass() || ! str_starts_with($source->type, 'App\\Features\\Customers\\Notifications\\')) {
                return;
            }
            $this->record($source->notifiable_id, 'notification:'.$source->id,
                data_get($source->data, 'title', 'DancePro update'), data_get($source->data, 'body', 'You have a new update.'),
                data_get($source->data, 'url'), 'View details', null, null, $source->created_at, $source->read_at);
        } elseif ($source instanceof ConcertTrackerEmailDelivery && $source->photo_release_group_id) {
            // Only gallery announcements belong in personal inboxes. Booking and
            // production correspondence remains in the authorised Studio Hub.
            if (! in_array($source->status, ['sent', 'simulated'], true)) {
                return;
            }
            $studioId = ConcertPhotoReleaseGroup::whereKey($source->photo_release_group_id)->value('studio_id');
            $sentAt = $source->sent_at ?? $source->queued_at;
            $user = $studioId && $sentAt ? User::whereNotNull('email_verified_at')
                ->where('created_at', '<=', $sentAt)->where('email_verified_at', '<=', $sentAt)
                ->whereIn('id', CustomerStudio::where('studio_id', $studioId)->whereNotNull('access_verified_at')
                    ->where('access_verified_at', '<=', $sentAt)->select('user_id'))
                ->whereRaw('lower(email) = ?', [mb_strtolower($source->recipient_email)])->first() : null;
            if ($user) {
                $this->record($user->id, 'photo-release:'.$source->id, $source->subject, $source->body,
                    route('customer.media.index'), 'Open My Events', $source->status, $source->sent_at, $source->sent_at ?? $source->queued_at);
            }
        }
    }

    private function record($userId, string $key, string $subject, string $body, ?string $url, ?string $label, ?string $status, $sentAt, $occurredAt, $readAt = null): void
    {
        if (! $this->availableAccount($userId)) {
            return;
        }
        $entry = CustomerInboxNotification::firstOrNew(['source_key' => $key]);
        if ($entry->exists && $entry->user_id !== (int) $userId) {
            return; // Never reassign old correspondence by matching a reused email.
        }
        if (! $entry->exists) {
            $entry->user_id = $userId;
            $entry->read_at = $readAt;
        }
        $entry->fill(['subject' => $subject, 'body' => $body, 'action_url' => $this->safeUrl($url), 'action_label' => $label,
            'email_status' => $status, 'email_sent_at' => $sentAt, 'occurred_at' => $occurredAt ?? now()])->save();
    }

    private function availableAccount($userId): bool
    {
        return $userId && User::whereKey($userId)->whereNull('anonymized_at')->whereNull('merged_into_user_id')->exists();
    }

    public function safeUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }
        $parts = parse_url($url);
        $host = parse_url(config('app.url'), PHP_URL_HOST);

        return is_array($parts) && in_array($parts['scheme'] ?? '', ['http', 'https'], true) && ($parts['host'] ?? null) === $host ? $url : null;
    }
}
