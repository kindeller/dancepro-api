<?php

namespace Tests\Feature\Payments;

use App\Features\Customers\Models\CustomerProfile;
use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Models\MediaCollection;
use App\Features\Media\Services\MediaCommerceSettings;
use App\Features\Media\Support\MediaType;
use App\Features\Orders\Models\Order;
use App\Features\Orders\Models\OrderItem;
use App\Features\Orders\Models\OrderMediaEntitlement;
use App\Features\Orders\Services\ActivateOrderMediaEntitlement;
use App\Features\Payments\Notifications\CustomerMediaEmail;
use App\Features\Payments\Services\CustomerMediaNotifications;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CustomerMediaNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipts_are_queued_once_for_each_paid_order(): void
    {
        Notification::fake();
        $customer = User::factory()->customer()->create();
        $order = Order::factory()->paid()->create([
            'user_id' => $customer->id,
            'total_amount' => 6500,
            'tax_amount' => 591,
        ]);

        $notifications = app(CustomerMediaNotifications::class);
        $first = $notifications->receipt($order);
        $again = $notifications->receipt($order);

        $this->assertTrue($first->is($again));
        $this->assertDatabaseCount('customer_media_notifications', 1);
        $this->assertDatabaseHas('customer_media_notifications', [
            'order_id' => $order->id,
            'type' => 'receipt',
            'recipient_email' => $customer->email,
            'status' => 'queued',
        ]);
        Notification::assertSentToTimes($customer, CustomerMediaEmail::class, 1);
    }

    public function test_ready_email_is_sent_once_and_respects_customer_preferences(): void
    {
        Notification::fake();
        $customer = User::factory()->customer()->create();
        CustomerProfile::factory()->for($customer)->create(['preferences' => [
            'media_ready_email' => true,
            'expiry_reminders_email' => true,
        ]]);
        [$item, $asset] = $this->paidPhotoItem($customer);

        $entitlement = app(ActivateOrderMediaEntitlement::class)
            ->execute($item, 'photo', collect([$asset]), notifyCustomer: true);
        app(CustomerMediaNotifications::class)->ready($entitlement);

        $this->assertDatabaseCount('customer_media_notifications', 1);
        $this->assertDatabaseHas('customer_media_notifications', [
            'order_media_entitlement_id' => $entitlement->id,
            'type' => 'media_ready',
        ]);
        Notification::assertSentToTimes($customer, CustomerMediaEmail::class, 1);

        $customer->customerProfile->update(['preferences' => [
            'media_ready_email' => false,
            'expiry_reminders_email' => true,
        ]]);
        [$mutedItem, $mutedAsset] = $this->paidPhotoItem($customer);
        app(ActivateOrderMediaEntitlement::class)
            ->execute($mutedItem, 'photo', collect([$mutedAsset]), notifyCustomer: true);
        $this->assertDatabaseCount('customer_media_notifications', 1);
    }

    public function test_expiry_reminders_only_include_undownloaded_media_and_are_deduplicated(): void
    {
        Notification::fake();
        $customer = User::factory()->customer()->create();
        CustomerProfile::factory()->for($customer)->create(['preferences' => [
            'media_ready_email' => true,
            'expiry_reminders_email' => true,
        ]]);
        [$item] = $this->paidPhotoItem($customer);
        OrderMediaEntitlement::create([
            'order_item_id' => $item->id,
            'media_type' => 'photo',
            'ready_at' => now()->subMonths(11),
            'expires_at' => now()->addDays(10)->setTime(15, 0),
        ]);
        [$downloadedItem] = $this->paidPhotoItem($customer);
        OrderMediaEntitlement::create([
            'order_item_id' => $downloadedItem->id,
            'media_type' => 'photo',
            'ready_at' => now()->subMonths(11),
            'expires_at' => now()->addDays(10)->setTime(15, 0),
            'first_download_at' => now()->subDay(),
        ]);

        $notifications = app(CustomerMediaNotifications::class);
        $this->assertSame(1, $notifications->sendExpiryReminders());
        $this->assertSame(0, $notifications->sendExpiryReminders());

        $this->assertDatabaseCount('customer_media_notifications', 1);
        $this->assertDatabaseHas('customer_media_notifications', ['type' => 'expiry_reminder']);
        Notification::assertSentToTimes($customer, CustomerMediaEmail::class, 1);
    }

    public function test_staff_can_edit_each_template_and_view_notification_tools(): void
    {
        Notification::fake();
        $staff = User::factory()->staff()->create();
        $this->actingAs($staff)->get(route('admin.media-settings.index', ['tab' => 'notifications']))
            ->assertOk()
            ->assertSee('Order confirmation')
            ->assertSee('Media ready')
            ->assertSee('Download expiry reminder')
            ->assertSee('Send test email')
            ->assertSee('Recent delivery history');

        $this->post(route('admin.media-settings.update', ['tab' => 'notifications']), [
            'section' => 'notifications',
            'notification_template' => 'receipt',
            'receipt_email_subject' => 'Your receipt from DancePro',
            'receipt_email_body' => 'Thanks for your purchase.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('Your receipt from DancePro', app(MediaCommerceSettings::class)
            ->get('receipt_email_subject'));

        $this->post(route('admin.media-settings.notifications.test'), [
            'template' => 'receipt',
            'email' => 'notifications@example.test',
        ])->assertRedirect(route('admin.media-settings.index', ['tab' => 'notifications']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Test email sent to notifications@example.test.');
        $this->assertDatabaseHas('customer_media_notifications', [
            'user_id' => $staff->id,
            'type' => 'test_receipt',
            'recipient_email' => 'notifications@example.test',
        ]);
        Notification::assertSentOnDemandTimes(CustomerMediaEmail::class);
    }

    /** @return array{OrderItem, MediaAsset} */
    private function paidPhotoItem(User $customer): array
    {
        $collection = MediaCollection::factory()->create(['media_type' => MediaType::Photo]);
        $asset = MediaAsset::factory()->create([
            'media_collection_id' => $collection->id,
            'media_type' => MediaType::Photo,
        ]);
        $order = Order::factory()->paid()->create(['user_id' => $customer->id]);
        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'media_collection_id' => $collection->id,
            'media_asset_id' => $asset->id,
            'item_type' => 'photo',
            'metadata' => ['asset_uuids' => [$asset->uuid], 'media_pending' => false],
        ]);

        return [$item, $asset];
    }
}
