<?php

namespace Tests\Feature\Customers;

use App\Features\Concerts\Models\Concert;
use App\Features\Customers\Notifications\CustomerAccountMessage;
use App\Features\Orders\Models\Order;
use App\Features\Orders\Models\OrderItem;
use App\Features\Payments\Models\CustomerCreditTransaction;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminCustomerAccountManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_the_complete_customer_account_workspace(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->customer()->create([
            'name' => 'Workspace Customer',
            'email' => 'workspace@example.test',
        ]);

        $this->actingAs($admin)->get(route('admin.customers.show', $customer))
            ->assertOk()
            ->assertSee('Workspace Customer')
            ->assertSee('Orders &amp; refunds', false)
            ->assertSee('Account settings');

        foreach (['settings', 'credit', 'orders', 'access', 'communications', 'downloads', 'favourites', 'cart', 'insights', 'security', 'notes', 'privacy', 'activity'] as $tab) {
            $this->get(route('admin.customers.show', [$customer, 'tab' => $tab]))->assertOk();
        }
    }

    public function test_admin_can_find_a_customer_by_their_order_number(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->customer()->create(['name' => 'Order Search Customer']);
        $other = User::factory()->customer()->create(['name' => 'Unrelated Customer']);
        $order = Order::factory()->paid()->create(['user_id' => $customer->id]);

        $this->actingAs($admin)->get(route('admin.customers.index', ['q' => '#'.$order->displayNumber()]))
            ->assertOk()
            ->assertSee('href="'.route('admin.customers.show', $customer).'"', false)
            ->assertDontSee('href="'.route('admin.customers.show', $other).'"', false);

        $this->get(route('admin.customers.show', [$customer, 'tab' => 'orders']))
            ->assertOk()
            ->assertSee('Order '.$order->displayNumber());
    }

    public function test_admin_can_pause_an_account_and_the_change_is_audited(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->customer()->create();

        $this->actingAs($admin)->patch(route('admin.customers.status.update', $customer), [
            'status' => 'paused',
            'reason' => 'Customer requested a temporary pause.',
        ])->assertRedirect();

        $this->assertFalse($customer->fresh()->is_active);
        $this->assertSame('paused', $customer->fresh()->account_status);
        $this->assertDatabaseHas('customer_account_activities', [
            'user_id' => $customer->id,
            'actor_user_id' => $admin->id,
            'type' => 'account_status',
        ]);
    }

    public function test_admin_can_add_credit_send_email_and_add_concert_access(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->customer()->create();
        $concert = Concert::factory()->create();

        $this->actingAs($admin)->post(route('admin.customers.credits.store', $customer), [
            'amount' => '25.50',
            'credit_type' => 'gift',
            'note' => 'Service recovery.',
        ])->assertRedirect();
        $this->post(route('admin.customers.emails.store', $customer), [
            'subject' => 'Your account update',
            'body' => 'We have added credit to your account.',
        ])->assertRedirect();
        $this->post(route('admin.customers.events.store', $customer), [
            'event_kind' => 'concert',
            'event_uuid' => $concert->uuid,
            'reason' => 'Customer could not redeem their supplied code.',
        ])->assertRedirect();

        $this->assertSame(2550, (int) CustomerCreditTransaction::where('user_id', $customer->id)->sum('amount'));
        $this->assertDatabaseHas('customer_account_emails', ['user_id' => $customer->id, 'status' => 'sent']);
        $this->assertDatabaseHas('concert_access_grants', ['user_id' => $customer->id, 'concert_id' => $concert->id, 'source' => 'admin_support']);
        Notification::assertSentTo($customer, CustomerAccountMessage::class);
    }

    public function test_admin_can_prepare_a_refund_for_selected_paid_products_only(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->customer()->create();
        $order = Order::factory()->paid()->create([
            'user_id' => $customer->id,
            'total_amount' => 3000,
            'tax_amount' => 273,
            'metadata' => ['source' => 'customer_cart', 'payment_amount' => 3000],
        ]);
        $selected = OrderItem::factory()->create([
            'order_id' => $order->id,
            'total_price_amount' => 2000,
            'tax_amount' => 182,
            'metadata' => ['net_after_account_credit' => 1500],
        ]);
        OrderItem::factory()->create(['order_id' => $order->id, 'total_price_amount' => 1000, 'tax_amount' => 91]);

        $this->actingAs($admin)->post(route('admin.customers.refunds.store', [$customer, $order]), [
            'order_item_ids' => [$selected->id],
            'reason' => 'Selected product was supplied incorrectly.',
            'revoke_access' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('order_refunds', [
            'order_id' => $order->id,
            'status' => 'pending_provider',
            'amount' => 1500,
            'tax_amount' => 182,
        ]);
        $this->assertDatabaseHas('order_refund_items', ['order_item_id' => $selected->id, 'amount' => 1500, 'revoke_access' => true]);
        $this->assertSame('paid', $order->fresh()->status->value);
    }

    public function test_admin_can_correct_contact_details_and_send_a_password_reset(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->customer()->create([
            'name' => 'Old Name',
            'email' => 'old@example.test',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)->patch(route('admin.customers.contact.update', $customer), [
            'name' => 'Correct Name',
            'preferred_name' => 'Correct',
            'email' => 'correct@example.test',
            'phone' => '0400 000 000',
            'reason' => 'Customer confirmed their corrected details.',
        ])->assertRedirect();

        $customer->refresh();
        $this->assertSame('Correct Name', $customer->name);
        $this->assertSame('correct@example.test', $customer->email);
        $this->assertNull($customer->email_verified_at);
        $this->assertSame('0400 000 000', $customer->customerProfile->phone);
        $this->assertDatabaseHas('customer_account_activities', ['user_id' => $customer->id, 'type' => 'contact_details_updated']);
        Notification::assertSentTo($customer, VerifyEmail::class);

        $this->post(route('admin.customers.password-reset.store', $customer))->assertRedirect();
        Notification::assertSentTo($customer, ResetPassword::class);
        $this->assertDatabaseHas('customer_account_activities', ['user_id' => $customer->id, 'type' => 'password_reset_requested']);
    }
}
