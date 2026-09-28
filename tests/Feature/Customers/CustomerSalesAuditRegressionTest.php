<?php

namespace Tests\Feature\Customers;

use App\Features\Concerts\Models\Concert;
use App\Features\Concerts\Models\ConcertAccessGrant;
use App\Features\Customers\Models\CustomerAccountNote;
use App\Features\Customers\Models\CustomerDancer;
use App\Features\Customers\Models\CustomerProfile;
use App\Features\Customers\Services\CustomerAccountAdministration;
use App\Features\Customers\Services\CustomerAccountIntelligence;
use App\Features\Customers\Services\CustomerCommunicationPreferences;
use App\Features\Customers\Services\MergeCustomerAccounts;
use App\Features\Orders\Models\Order;
use App\Features\Orders\Models\OrderItem;
use App\Features\Orders\Models\OrderMediaEntitlement;
use App\Features\Orders\Models\OrderRefund;
use App\Features\Payments\Models\CustomerMediaNotification;
use App\Features\Payments\Notifications\CustomerMediaEmail;
use App\Features\Payments\Services\CustomerMediaNotifications;
use App\Features\Reporting\Services\MediaSalesReport;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CustomerSalesAuditRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_unchecked_admin_preferences_disable_the_actual_notification_channels(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->customer()->create();
        CustomerProfile::factory()->create(['user_id' => $customer->id, 'marketing_consent_at' => now(), 'preferences' => ['media_ready_email' => true, 'expiry_reminders_email' => true]]);
        $this->actingAs($admin)->patch(route('admin.customers.preferences.update', $customer), ['reason' => 'Customer opted out.'])->assertRedirect()->assertSessionHasNoErrors();
        foreach (['marketing_email', 'media_ready_email', 'expiry_reminders_email'] as $key) {
            $this->assertFalse(CustomerCommunicationPreferences::enabled($customer->fresh(), $key));
            $this->assertDatabaseHas('customer_consent_histories', ['user_id' => $customer->id, 'channel' => $key, 'granted' => false]);
        }
    }

    public function test_separate_entitlements_in_one_order_get_separate_ready_notifications(): void
    {
        Notification::fake();
        $customer = User::factory()->customer()->create();
        $order = Order::factory()->paid()->create(['user_id' => $customer->id]);
        foreach (range(1, 2) as $index) {
            $item = OrderItem::factory()->create(['order_id' => $order->id]);
            $entitlement = OrderMediaEntitlement::create(['order_item_id' => $item->id, 'media_type' => 'video', 'ready_at' => now()]);
            app(CustomerMediaNotifications::class)->ready($entitlement);
            app(CustomerMediaNotifications::class)->ready($entitlement);
        }
        $this->assertDatabaseCount('customer_media_notifications', 2);
        Notification::assertSentToTimes($customer, CustomerMediaEmail::class, 2);
    }

    public function test_mixed_event_reports_scope_cash_and_refunds_and_exclude_grants_from_value(): void
    {
        $customer = User::factory()->customer()->create();
        $event = SchedulingEvent::create(['name' => 'Report competition', 'event_type' => 'competition', 'event_date' => now()]);
        $concert = Concert::factory()->create();
        $order = Order::factory()->paid()->create(['user_id' => $customer->id, 'subtotal_amount' => 10000, 'total_amount' => 7000,
            'metadata' => ['source' => 'customer_cart', 'kind' => 'account_cart', 'source_uuid' => 'selected-events', 'payment_amount' => 7000, 'account_credit_amount' => 1000]]);
        $comp = OrderItem::factory()->create(['order_id' => $order->id, 'unit_price_amount' => 6000, 'total_price_amount' => 5000,
            'metadata' => ['event_kind' => 'competition', 'event_uuid' => $event->uuid, 'net_after_account_credit' => 4500, 'account_credit_amount' => 500]]);
        OrderItem::factory()->create(['order_id' => $order->id, 'unit_price_amount' => 4000, 'total_price_amount' => 3000,
            'metadata' => ['event_kind' => 'concert', 'event_uuid' => $concert->uuid, 'net_after_account_credit' => 2500, 'account_credit_amount' => 500]]);
        $refund = OrderRefund::create(['order_id' => $order->id, 'status' => 'completed', 'amount' => 1500, 'tax_amount' => 0, 'currency' => 'AUD', 'reason' => 'Partial refund', 'requested_at' => now()]);
        $refund->items()->create(['order_item_id' => $comp->id, 'amount' => 1500, 'tax_amount' => 0]);
        Order::factory()->paid()->create(['user_id' => $customer->id, 'total_amount' => 0, 'metadata' => ['source' => 'complimentary_media_grant']]);
        $report = app(MediaSalesReport::class)->report(['kind' => 'competition']);
        $this->assertSame(4500, $report['summary']['sales']);
        $this->assertSame(1500, $report['summary']['refunds']);
        $this->assertSame(3000, $report['summary']['net_receipts']);
        $this->assertSame(4500, $report['products']->sum('sales'));
        $this->assertFalse($report['events']['rows']->contains('uuid', $concert->uuid));
        $metrics = app(CustomerAccountIntelligence::class)->metrics($customer);
        $this->assertSame(1, $metrics['order_count']);
        $this->assertSame(2, $metrics['purchased_events']);
        $this->assertSame(5500, $metrics['lifetime_value']);
    }

    public function test_anonymisation_redacts_the_stored_soft_deleted_profile_and_blocks_reactivation(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->customer()->create();
        CustomerProfile::factory()->create(['user_id' => $customer->id, 'phone' => '0400000000', 'preferred_name' => 'Private Name', 'preferences' => ['private_note' => 'Private value']]);
        app(CustomerAccountIntelligence::class)->anonymize($customer, $admin, $customer->email, 'Account closure');
        $profile = DB::table('customer_profiles')->where('user_id', $customer->id)->first();
        $this->assertNull($profile->phone);
        $this->assertNull($profile->preferred_name);
        $this->assertNull($profile->preferences);
        $this->actingAs($admin)->patch(route('admin.customers.status.update', $customer), ['status' => 'active', 'reason' => 'Try restore'])->assertStatus(409);
    }

    public function test_expired_revoked_concert_access_can_be_renewed_without_duplicate_grants(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->customer()->create();
        $concert = Concert::factory()->create();
        app(CustomerAccountAdministration::class)->event($customer, $admin, 'concert', $concert->uuid, 'Support');
        $grant = ConcertAccessGrant::where('user_id', $customer->id)->sole();
        $grant->update(['expires_at' => now()->subDay(), 'revoked_at' => now(), 'revoke_reason' => 'Expired']);
        app(CustomerAccountAdministration::class)->event($customer, $admin, 'concert', $concert->uuid, 'Renew');
        $this->assertDatabaseCount('concert_access_grants', 1);
        $this->assertNull($grant->fresh()->revoked_at);
        $this->assertTrue($grant->fresh()->expires_at->isFuture());
    }

    public function test_workspace_histories_and_order_queue_are_navigable_and_paginated(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->customer()->create();
        $orders = Order::factory()->count(28)->create(['user_id' => $customer->id]);
        $this->actingAs($admin)->get(route('admin.customers.show', [$customer, 'tab' => 'orders', 'orders_page' => 2]))->assertOk()->assertViewHas('orders', fn ($rows) => $rows->count() === 3);
        $this->get(route('admin.orders.index', ['q' => $orders->first()->displayNumber()]))->assertOk()->assertSee($orders->first()->displayNumber())->assertViewHas('orders', fn ($rows) => $rows->total() === 1);
        foreach (['orders', 'refunds', 'issues'] as $tab) {
            $this->get(route('admin.orders.index', ['tab' => $tab]))->assertOk();
        }
        $this->actingAs($customer)->get(route('admin.orders.index'))->assertForbidden();
    }

    public function test_followup_queue_shows_due_work_and_completion_removes_it(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->customer()->create();
        $note = CustomerAccountNote::create(['user_id' => $customer->id, 'note' => 'Call this customer', 'follow_up_at' => now()->subHour()]);
        CustomerAccountNote::create(['user_id' => $customer->id, 'note' => 'Future action', 'follow_up_at' => now()->addDay()]);
        $this->actingAs($admin)->get(route('admin.customers.follow-ups'))->assertOk()->assertSee('Call this customer')->assertDontSee('Future action');
        $this->patch(route('admin.customers.notes.complete', [$customer, $note]))->assertRedirect();
        $this->get(route('admin.customers.follow-ups'))->assertOk()->assertDontSee('Call this customer');
        $this->get(route('admin.customers.follow-ups', ['scope' => 'completed']))->assertOk()->assertSee('Call this customer');
    }

    public function test_merge_preserves_aliases_and_handles_a_soft_deleted_primary_profile(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->customer()->create();
        $source = User::factory()->customer()->create();
        CustomerProfile::factory()->create(['user_id' => $target->id])->delete();
        CustomerProfile::factory()->create(['user_id' => $source->id, 'phone' => '0401234567']);
        CustomerDancer::create(['user_id' => $target->id, 'name' => 'Alex Smith', 'programme_names' => ['Alex S']]);
        CustomerDancer::create(['user_id' => $source->id, 'name' => 'Alex Smith', 'programme_names' => ['A Smith']]);
        app(MergeCustomerAccounts::class)->execute($source, $target, $admin, $target->email, 'Same customer');
        $this->assertEqualsCanonicalizing(['Alex S', 'A Smith'], CustomerDancer::where('user_id', $target->id)->sole()->programme_names);
        $this->assertSame($target->id, $source->fresh()->merged_into_user_id);
    }

    public function test_queued_ready_notice_rechecks_consent_before_sending(): void
    {
        $customer = User::factory()->customer()->create();
        $delivery = CustomerMediaNotification::create([
            'user_id' => $customer->id, 'type' => 'media_ready', 'deduplication_key' => 'queued-test',
            'recipient_email' => $customer->email, 'subject' => 'Ready', 'status' => 'queued', 'queued_at' => now(),
        ]);
        $notification = new CustomerMediaEmail($delivery->id, 'Ready', 'Ready', 'Media', 'http://localhost/my/media');
        $this->assertTrue($notification->shouldSend($customer, 'mail'));
        app(CustomerCommunicationPreferences::class)->save($customer, []);
        $this->assertFalse($notification->shouldSend($customer, 'mail'));
        $this->assertSame('suppressed', $delivery->fresh()->status);
    }

    public function test_studio_activity_form_keeps_the_studio_route(): void
    {
        $admin = User::factory()->admin()->create();
        $studio = Studio::factory()->create();
        Concert::factory()->create(['studio_id' => $studio->id]);
        $url = route('admin.media-sales.studios.activity', $studio);
        $this->actingAs($admin)->get($url.'?activity=all')->assertOk()
            ->assertSee('action="'.$url.'"', false)->assertSee('href="'.$url.'"', false);
    }
}
