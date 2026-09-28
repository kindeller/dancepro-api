<?php

namespace Tests\Feature\Customers;

use App\Features\Concerts\Models\ConcertAccessGrant;
use App\Features\Customers\Notifications\CustomerAccountMessage;
use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Models\MediaCollection;
use App\Features\Orders\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminComplimentaryMediaGrantTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_grant_an_individual_photo_to_a_customer_by_email_without_creating_a_sale(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->customer()->create(['email' => 'recipient@example.test']);
        $collection = MediaCollection::factory()->create([
            'media_type' => 'photo', 'status' => 'published', 'visibility' => 'customer', 'published_at' => now(),
        ]);
        $asset = MediaAsset::factory()->for($collection, 'collection')->create();

        $this->actingAs($admin)->post(route('admin.customers.complimentary-media.store'), [
            'customer_lookup' => 'recipient@example.test',
            'source_kind' => 'concert_photo',
            'event_uuid' => $collection->concert->uuid,
            'source_uuid' => $asset->uuid,
            'note' => 'Customer service gift',
        ])->assertRedirect(route('admin.customers.show', $customer));

        $order = Order::where('user_id', $customer->id)->sole();
        $this->assertSame('fulfilled', $order->status->value);
        $this->assertSame(0, $order->total_amount);
        $this->assertSame('complimentary_media_grant', $order->metadata['source']);
        $this->assertSame($admin->id, $order->metadata['granted_by_user_id']);
        $this->assertDatabaseHas('order_media_entitlement_assets', ['media_asset_id' => $asset->id]);
        $access = ConcertAccessGrant::where('concert_id', $collection->concert_id)->where('user_id', $customer->id)->sole();
        $this->assertSame('order', $access->source->value);
        $this->assertSame('claimed', $access->status->value);
    }

    public function test_an_existing_entitlement_is_not_granted_twice(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->customer()->create();
        $collection = MediaCollection::factory()->create([
            'media_type' => 'photo', 'status' => 'published', 'visibility' => 'customer', 'published_at' => now(),
        ]);
        $asset = MediaAsset::factory()->for($collection, 'collection')->create();
        $payload = ['customer_lookup' => (string) $customer->id, 'source_kind' => 'concert_photo', 'source_uuid' => $asset->uuid];

        $this->actingAs($admin)->post(route('admin.customers.complimentary-media.store'), $payload)->assertRedirect();
        $this->post(route('admin.customers.complimentary-media.store'), $payload)
            ->assertSessionHasErrors('source_uuid');

        $this->assertSame(1, Order::where('user_id', $customer->id)->count());
    }

    public function test_customer_directory_searches_by_account_id_and_email(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->customer()->create(['name' => 'Review Customer', 'email' => 'review@example.test']);

        $this->actingAs($admin)->get(route('admin.customers.index', ['q' => (string) $customer->id]))
            ->assertOk()->assertSee('Review Customer')->assertSee('ID #'.$customer->id);
        $this->get(route('admin.customers.index', ['q' => 'review@example.test']))
            ->assertOk()->assertSee(route('admin.customers.show', $customer), false);
    }

    public function test_admin_customer_suggestions_show_id_name_and_email(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->customer()->create([
            'name' => 'Taylor Example',
            'email' => 'taylor@example.test',
        ]);
        User::factory()->customer()->create(['name' => 'Different Customer', 'email' => 'different@example.test']);

        $this->actingAs($admin)->getJson(route('admin.customers.search', ['q' => 'Taylor']))
            ->assertOk()
            ->assertExactJson(['data' => [[
                'id' => $customer->id,
                'name' => 'Taylor Example',
                'email' => 'taylor@example.test',
            ]]]);

        $this->getJson(route('admin.customers.search', ['q' => (string) $customer->id]))
            ->assertOk()
            ->assertJsonPath('data.0.id', $customer->id);
    }

    public function test_admin_can_notify_about_and_later_revoke_a_complimentary_grant(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->customer()->create();
        $collection = MediaCollection::factory()->create([
            'media_type' => 'photo', 'status' => 'published', 'visibility' => 'customer', 'published_at' => now(),
        ]);
        $asset = MediaAsset::factory()->for($collection, 'collection')->create();

        $this->actingAs($admin)->post(route('admin.customers.complimentary-media.store'), [
            'customer_lookup' => $customer->email,
            'source_kind' => 'concert_photo',
            'source_uuid' => $asset->uuid,
            'notify_customer' => true,
        ])->assertRedirect();

        $order = Order::where('user_id', $customer->id)->sole();
        Notification::assertSentTo($customer, CustomerAccountMessage::class);
        $this->assertDatabaseHas('customer_account_emails', ['user_id' => $customer->id, 'status' => 'sent']);

        $this->post(route('admin.customers.complimentary-media.revoke', [$customer, $order]), [
            'reason' => 'Granted to the wrong account.',
            'notify_customer' => true,
        ])->assertRedirect();

        $entitlement = $order->items()->firstOrFail()->mediaEntitlements()->firstOrFail();
        $this->assertNotNull($entitlement->revoked_at);
        $this->assertSame($admin->id, $entitlement->revoked_by_user_id);
        $this->assertDatabaseHas('customer_account_activities', [
            'user_id' => $customer->id,
            'type' => 'complimentary_media_revoked',
        ]);
        Notification::assertSentToTimes($customer, CustomerAccountMessage::class, 2);
    }
}
