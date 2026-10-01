<?php

namespace Tests\Feature\Customers;

use App\Features\Customers\Models\CustomerHelpArticle;
use App\Features\Customers\Models\CustomerSupportRequest;
use App\Features\Customers\Notifications\CustomerSupportRequestUpdated;
use App\Features\Orders\Models\Order;
use App\Features\Orders\Models\OrderItem;
use App\Features\Orders\Models\OrderMediaEntitlement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CustomerMediaSupportTest extends TestCase
{
    use RefreshDatabase;

    public function test_help_centre_searches_placeholder_articles_and_records_feedback(): void
    {
        $customer = User::factory()->customer()->create(['onboarding_completed_at' => now()]);

        $response = $this->actingAs($customer)->get(route('customer.media.help', ['q' => 'expired download']))
            ->assertOk()->assertSee('What if my download period has ended?')
            ->assertDontSee('Why are gallery photos watermarked?');
        $this->assertDatabaseHas('customer_help_searches', [
            'user_id' => $customer->id, 'query' => 'expired download', 'result_count' => $response->viewData('articleGroups')->sum(fn ($articles) => $articles->count()),
        ]);

        $article = CustomerHelpArticle::query()->where('slug', 'what-if-my-download-period-has-ended')->firstOrFail();
        $this->post(route('customer.media.help.feedback', $article), ['helpful' => true])
            ->assertRedirect()->assertSessionHas('status');
        $this->assertDatabaseHas('customer_help_article_feedback', [
            'customer_help_article_id' => $article->id, 'user_id' => $customer->id, 'helpful' => true,
        ]);

        $this->get(route('customer.media.help', ['q' => 'unfindable-question-xyz']))
            ->assertOk()->assertSee('No answer found');
        $this->assertDatabaseHas('customer_help_searches', ['query' => 'unfindable-question-xyz', 'result_count' => 0]);
    }

    public function test_customer_can_request_reactivation_for_their_expired_media(): void
    {
        [$customer, $order, $entitlement] = $this->expiredEntitlement();

        $this->actingAs($customer)->post(route('customer.media.help.store'), [
            'type' => 'download_reactivation',
            'order_id' => $order->id,
            'order_media_entitlement_id' => $entitlement->id,
            'subject' => 'Please restore my photos',
            'message' => 'I need another copy for my family.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $request = CustomerSupportRequest::query()->firstOrFail();
        $this->assertSame($customer->id, $request->user_id);
        $this->assertSame($order->uuid, $request->context['order_uuid']);
        $this->assertSame($order->displayNumber(), $request->context['order_number']);
        $this->assertSame('photo', $request->context['media_type']);
        $this->assertDatabaseHas('customer_support_requests', ['status' => 'pending', 'order_media_entitlement_id' => $entitlement->id]);

        $this->actingAs($customer)->get(route('customer.messages.show', $request))
            ->assertOk()->assertSee('Please restore my photos')->assertSee('Awaiting DancePro reply');
    }

    public function test_expired_download_link_opens_the_preselected_reactivation_form(): void
    {
        [$customer, $order, $entitlement] = $this->expiredEntitlement();
        $asset = $entitlement->orderItem->mediaAsset;

        $response = $this->actingAs($customer)->get(route('customer.purchases.download', [$entitlement, $asset]));

        $response->assertRedirect(route('customer.media.help', [
            'type' => 'download_reactivation', 'order' => $order->id, 'entitlement' => $entitlement->id,
        ]))->assertSessionHas('status', 'This download period has ended. You can request reactivation below.');
    }

    public function test_customer_cannot_request_another_customers_entitlement_or_duplicate_a_pending_request(): void
    {
        [$customer, , $entitlement] = $this->expiredEntitlement();
        $other = User::factory()->customer()->create(['onboarding_completed_at' => now()]);
        $payload = [
            'type' => 'download_reactivation', 'order_media_entitlement_id' => $entitlement->id,
            'subject' => 'Restore access', 'message' => 'Please restore this download.',
        ];

        $this->actingAs($other)->post(route('customer.media.help.store'), $payload)
            ->assertSessionHasErrors('order_media_entitlement_id');
        $this->actingAs($customer)->post(route('customer.media.help.store'), $payload)->assertSessionHasNoErrors();
        $this->post(route('customer.media.help.store'), $payload)->assertSessionHasErrors('order_media_entitlement_id');
    }

    public function test_admin_approval_extends_only_the_requested_entitlement_and_notifies_the_customer(): void
    {
        Notification::fake();
        [$customer, $order, $entitlement] = $this->expiredEntitlement();
        $otherEntitlement = OrderMediaEntitlement::query()->create([
            'order_item_id' => $entitlement->order_item_id, 'media_type' => 'video',
            'ready_at' => now()->subMonths(3), 'expires_at' => now()->subMonth(),
        ]);
        $supportRequest = CustomerSupportRequest::query()->create([
            'user_id' => $customer->id, 'order_id' => $order->id,
            'order_media_entitlement_id' => $entitlement->id, 'type' => 'download_reactivation',
            'subject' => 'Restore access', 'message' => 'Please help.', 'status' => 'pending',
            'requested_at' => now(), 'context' => ['order_uuid' => $order->uuid],
        ]);
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);

        $this->actingAs($admin)->get(route('admin.messages.index', ['channel' => 'customer', 'status' => 'pending']))
            ->assertOk()->assertSee('Restore access')->assertSee($customer->email);
        $this->actingAs($admin)->patch(route('admin.media-support.resolve', $supportRequest), [
            'decision' => 'approved', 'extension_days' => 30, 'response_note' => 'Access restored for 30 days.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertTrue($entitlement->fresh()->expires_at->isAfter(now()->addDays(29)));
        $this->assertTrue($otherEntitlement->expires_at->equalTo($otherEntitlement->fresh()->expires_at));
        $this->assertDatabaseHas('order_media_entitlement_extension_audits', [
            'order_media_entitlement_id' => $entitlement->id,
            'customer_support_request_id' => $supportRequest->id,
            'actor_user_id' => $admin->id,
            'extension_days' => 30,
        ]);
        $this->assertDatabaseHas('customer_support_requests', ['id' => $supportRequest->id, 'status' => 'approved']);
        Notification::assertSentTo($customer, CustomerSupportRequestUpdated::class);
    }

    private function expiredEntitlement(): array
    {
        $customer = User::factory()->customer()->create(['onboarding_completed_at' => now()]);
        $order = Order::factory()->paid()->create(['user_id' => $customer->id]);
        $item = OrderItem::factory()->create(['order_id' => $order->id, 'snapshot_display_name' => 'Junior Jazz photos']);
        $entitlement = OrderMediaEntitlement::query()->create([
            'order_item_id' => $item->id, 'media_type' => 'photo', 'ready_at' => now()->subYear(),
            'expires_at' => now()->subDay(), 'download_count' => 2,
        ]);
        $entitlement->assets()->attach($item->media_asset_id);

        return [$customer, $order, $entitlement];
    }
}
