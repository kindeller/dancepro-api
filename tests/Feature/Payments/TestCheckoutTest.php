<?php

namespace Tests\Feature\Payments;

use App\Features\Customers\Models\CustomerMediaSelection;
use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Models\MediaCollection;
use App\Features\Media\Models\MediaDiscountCode;
use App\Features\Media\Support\MediaType;
use App\Features\Orders\Models\Order;
use App\Features\Orders\Models\OrderItem;
use App\Features\Orders\Services\ActivateOrderMediaEntitlement;
use App\Features\Payments\Services\ProcessTestPayment;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TestCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_test_payment_is_idempotent_and_activates_exact_media(): void
    {
        config(['payments.driver' => 'fake']);
        $user = User::factory()->customer()->create();
        $collection = MediaCollection::factory()->create(['media_type' => MediaType::Photo]);
        $asset = MediaAsset::factory()->create(['media_collection_id' => $collection->id, 'media_type' => MediaType::Photo]);
        $code = MediaDiscountCode::create(['code' => 'LOCAL10', 'percent' => 10, 'enabled' => true,
            'max_redemptions' => 2, 'max_redemptions_per_customer' => 1]);
        $order = Order::factory()->create(['user_id' => $user->id, 'total_amount' => 900, 'metadata' => [
            'source' => 'factory', 'discount_code_id' => $code->id, 'discount_code' => $code->code,
            'discount_code_type' => 'percent', 'discount_code_percent' => 10,
            'discount_code_configured_amount' => null, 'discount_code_amount' => 100,
        ]]);
        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'media_collection_id' => $collection->id,
            'media_asset_id' => $asset->id,
            'item_type' => 'photo',
            'metadata' => ['asset_uuids' => [$asset->uuid], 'media_pending' => false],
        ]);
        $key = (string) Str::uuid();

        $first = app(ProcessTestPayment::class)->execute($user, $order, $key, 'approved');
        $again = app(ProcessTestPayment::class)->execute($user, $order, $key, 'approved');

        $this->assertTrue($first->is($again));
        $this->assertSame('paid', $order->fresh()->status->value);
        $this->assertNotNull($order->fresh()->paid_at);
        $this->assertDatabaseCount('payment_attempts', 1);
        $this->assertDatabaseHas('media_discount_redemptions', ['order_id' => $order->id,
            'media_discount_code_id' => $code->id, 'user_id' => $user->id, 'amount' => 100]);
        $entitlement = $item->mediaEntitlements()->firstOrFail();
        $this->assertSame([$asset->id], $entitlement->assets()->pluck('media_assets.id')->all());
        $this->assertTrue($entitlement->expires_at->isAfter(now()->addMonths(11)));

        $second = Order::factory()->create(['user_id' => $user->id, 'metadata' => $order->metadata]);
        try {
            app(ProcessTestPayment::class)->execute($user, $second, (string) Str::uuid(), 'approved');
            $this->fail('The per-customer discount limit was ignored.');
        } catch (ValidationException $exception) {
            $this->assertSame('You have already used this discount code.', $exception->errors()['discount_code'][0]);
        }
    }

    public function test_declined_test_payment_leaves_the_order_unpaid(): void
    {
        config(['payments.driver' => 'fake']);
        $user = User::factory()->customer()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);

        $attempt = app(ProcessTestPayment::class)->execute($user, $order, (string) Str::uuid(), 'declined');

        $this->assertSame('declined', $attempt->status);
        $this->assertSame('draft', $order->fresh()->status->value);
        $this->assertNull($order->fresh()->paid_at);
        $this->assertDatabaseCount('order_media_entitlements', 0);
    }

    public function test_checkout_page_is_private_and_clearly_identifies_test_mode(): void
    {
        config(['payments.driver' => 'fake']);
        $owner = User::factory()->customer()->create();
        $stranger = User::factory()->customer()->create();
        $order = Order::factory()->create(['user_id' => $owner->id, 'total_amount' => 1500, 'tax_amount' => 136, 'metadata' => [
            'account_credit_amount' => 2500,
            'payment_amount' => 1500,
            'total_before_account_credit' => 4000,
        ]]);
        OrderItem::factory()->create(['order_id' => $order->id]);

        $this->actingAs($owner)->get(route('customer.checkout.show', $order))
            ->assertOk()
            ->assertSee('Test checkout')
            ->assertSee('Included GST')
            ->assertSee('Total including GST')
            ->assertSee('$1.36')
            ->assertSee('Account credit')
            ->assertSee('−$25.00')
            ->assertSee('$15.00')
            ->assertSee('No money will be charged')
            ->assertSee('Approve test payment');
        $this->actingAs($stranger)->get(route('customer.checkout.show', $order))->assertNotFound();
    }

    public function test_changed_cart_cannot_pay_an_old_prepared_order(): void
    {
        config(['payments.driver' => 'fake']);
        $user = User::factory()->customer()->create();
        $event = SchedulingEvent::create(['name' => 'Changed cart', 'event_type' => 'competition', 'event_date' => now()]);
        $selection = CustomerMediaSelection::create([
            'user_id' => $user->id, 'scheduling_event_id' => $event->id,
            'selection_key' => 'photo:'.Str::uuid(), 'product' => 'photo',
            'item_uuid' => (string) Str::uuid(), 'photo_uuid' => (string) Str::uuid(), 'in_cart' => true,
        ]);
        $order = Order::factory()->create(['user_id' => $user->id, 'metadata' => [
            'source' => 'customer_cart', 'kind' => 'competition', 'source_uuid' => $event->uuid,
            'cart_selection_ids' => [$selection->id],
        ]]);
        $selection->update(['in_cart' => false]);

        try {
            app(ProcessTestPayment::class)->execute($user, $order, (string) Str::uuid(), 'approved');
            $this->fail('A stale checkout was approved.');
        } catch (ValidationException $exception) {
            $this->assertSame('Your cart changed after this order was prepared. Return to the cart and review the updated order.', $exception->errors()['payment'][0]);
        }

        $this->assertSame('draft', $order->fresh()->status->value);
        $this->assertDatabaseCount('payment_attempts', 0);
    }

    public function test_customer_download_creates_a_short_lived_tracked_handoff(): void
    {
        $user = User::factory()->customer()->create();
        $collection = MediaCollection::factory()->create(['media_type' => MediaType::Photo]);
        $asset = MediaAsset::factory()->create(['media_collection_id' => $collection->id, 'media_type' => MediaType::Photo]);
        $order = Order::factory()->paid()->create(['user_id' => $user->id]);
        $item = OrderItem::factory()->create(['order_id' => $order->id, 'media_collection_id' => $collection->id,
            'media_asset_id' => $asset->id, 'item_type' => 'photo']);
        $entitlement = app(ActivateOrderMediaEntitlement::class)
            ->execute($item, 'photo', collect([$asset]));

        $response = $this->actingAs($user)->get(route('customer.purchases.download', [$entitlement, $asset]));

        $response->assertRedirectContains('/download/');
        $this->assertDatabaseHas('download_links', [
            'order_item_id' => $item->id,
            'media_asset_id' => $asset->id,
            'purpose' => 'customer_purchase',
        ]);
        $this->assertSame(1, $entitlement->fresh()->download_count);
        $this->assertDatabaseHas('order_media_asset_downloads', [
            'order_media_entitlement_id' => $entitlement->id,
            'media_asset_id' => $asset->id,
            'user_id' => $user->id,
            'download_count' => 1,
        ]);

        $this->actingAs($user)->get(route('customer.purchases.download', [$entitlement, $asset]))->assertRedirectContains('/download/');
        $this->assertDatabaseHas('order_media_asset_downloads', [
            'order_media_entitlement_id' => $entitlement->id,
            'media_asset_id' => $asset->id,
            'download_count' => 2,
        ]);

        $other = User::factory()->customer()->create();
        $this->actingAs($other)->get(route('customer.purchases.download', [$entitlement, $asset]))->assertNotFound();
    }
}
