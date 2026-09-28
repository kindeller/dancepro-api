<?php

namespace App\Features\Payments\Services;

use App\Features\Customers\Services\CustomerCommunicationPreferences;
use App\Features\Media\Services\MediaCommerceSettings;
use App\Features\Orders\Models\Order;
use App\Features\Orders\Models\OrderMediaEntitlement;
use App\Features\Payments\Models\CustomerMediaNotification;
use App\Features\Payments\Notifications\CustomerMediaEmail;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class CustomerMediaNotifications
{
    public function __construct(private readonly MediaCommerceSettings $settings) {}

    public function receipt(Order $order): ?CustomerMediaNotification
    {
        $order->loadMissing('user');
        if (! $order->user) {
            return null;
        }
        $body = $this->settings->get('receipt_email_body')."\n\nOrder ".$order->displayNumber()."\n"
            .'Total: $'.number_format($order->total_amount / 100, 2).' AUD (including $'
            .number_format($order->tax_amount / 100, 2).' GST).';

        return $this->dispatch($order->user, 'receipt', 'receipt:'.$order->id,
            $this->settings->get('receipt_email_subject'), $body, 'View order', route('customer.checkout.show', $order), $order);
    }

    public function ready(OrderMediaEntitlement $entitlement): ?CustomerMediaNotification
    {
        $entitlement->loadMissing('orderItem.order.user.customerProfile');
        $order = $entitlement->orderItem->order;
        $user = $order->user;
        if (! $user || ! $this->preference($user, 'media_ready_email')) {
            return null;
        }
        $label = ucfirst($entitlement->media_type);
        $body = $this->settings->get('ready_email_body')."\n\n{$label} media from order ".$order->displayNumber().' is ready.';

        return $this->dispatch($user, 'media_ready', "ready-entitlement:{$entitlement->id}",
            $this->settings->get('ready_email_subject'), $body, 'View my media', route('customer.checkout.show', $order),
            $order, $entitlement);
    }

    public function sendExpiryReminders(): int
    {
        $from = now()->addDays(10)->startOfDay();
        $until = now()->addDays(10)->endOfDay();
        $groups = OrderMediaEntitlement::query()->whereNotNull('ready_at')->whereNull('first_download_at')
            ->whereBetween('expires_at', [$from, $until])
            ->with('orderItem.order.user.customerProfile')->get()
            ->groupBy(fn (OrderMediaEntitlement $entitlement): string => $entitlement->orderItem->order_id.':'.$entitlement->expires_at->toDateString());
        $sent = 0;
        foreach ($groups as $entitlements) {
            $entitlement = $entitlements->first();
            $order = $entitlement->orderItem->order;
            $user = $order->user;
            if (! $user || ! $this->preference($user, 'expiry_reminders_email')) {
                continue;
            }
            $date = $entitlement->expires_at->format('j M Y');
            $types = $entitlements->pluck('media_type')->unique()->map(fn (string $type): string => ucfirst($type))->join(' and ');
            $body = $this->settings->get('expiry_email_body')."\n\n{$types} media from order ".$order->displayNumber()." expires on {$date}.";
            $delivery = $this->dispatch($user, 'expiry_reminder', "expiry:{$order->id}:{$entitlement->expires_at->toDateString()}",
                $this->settings->get('expiry_email_subject'), $body, 'Download my media', route('customer.checkout.show', $order),
                $order, $entitlement, ['entitlement_ids' => $entitlements->pluck('id')->all()]);
            $sent += $delivery?->wasRecentlyCreated ? 1 : 0;
        }

        return $sent;
    }

    public function sendTest(User $actor, string $template, string $email): CustomerMediaNotification
    {
        $prefix = match ($template) {
            'receipt' => 'receipt',
            'ready' => 'ready',
            default => 'expiry',
        };
        $subject = $this->settings->get($prefix.'_email_subject');
        $body = $this->settings->get($prefix.'_email_body')."\n\nThis is a test using sample order 100001.";
        $delivery = CustomerMediaNotification::create([
            'user_id' => $actor->id, 'type' => 'test_'.$template, 'deduplication_key' => 'test:'.Str::uuid(),
            'recipient_email' => $email, 'subject' => $subject, 'status' => 'queued', 'queued_at' => now(),
            'metadata' => ['template' => $template, 'test' => true],
        ]);
        Notification::route('mail', $email)->notifyNow(new CustomerMediaEmail(
            $delivery->id, '[TEST] '.$subject, $body, 'Open My Media', route('customer.media.index'),
        ));

        return $delivery->fresh();
    }

    private function dispatch(
        User $user,
        string $type,
        string $key,
        string $subject,
        string $body,
        string $actionLabel,
        string $actionUrl,
        ?Order $order = null,
        ?OrderMediaEntitlement $entitlement = null,
        array $metadata = [],
    ): CustomerMediaNotification {
        $delivery = CustomerMediaNotification::firstOrCreate(['deduplication_key' => $key], [
            'user_id' => $user->id, 'order_id' => $order?->id,
            'order_media_entitlement_id' => $entitlement?->id, 'type' => $type,
            'recipient_email' => $user->email, 'subject' => $subject, 'status' => 'queued',
            'queued_at' => now(), 'metadata' => $metadata,
        ]);
        if ($delivery->wasRecentlyCreated) {
            $user->notify((new CustomerMediaEmail($delivery->id, $subject, $body, $actionLabel, $actionUrl))->afterCommit());
        }

        return $delivery;
    }

    private function preference(User $user, string $key): bool
    {
        return CustomerCommunicationPreferences::enabled($user, $key);
    }
}
