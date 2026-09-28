<?php

namespace Tests\Feature\Customers;

use App\Features\Customers\Models\CustomerAccountNote;
use App\Features\Customers\Models\CustomerConsentHistory;
use App\Features\Customers\Models\CustomerRiskEvent;
use App\Features\Orders\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCustomerIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_record_preferences_notes_risk_and_export_customer_data(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->customer()->create();
        $order = Order::factory()->paid()->create(['user_id' => $customer->id, 'total_amount' => 5000, 'metadata' => ['payment_amount' => 5000]]);
        $this->actingAs($admin)->patch(route('admin.customers.preferences.update', $customer), ['marketing_email' => 1, 'service_email' => 1, 'download_email' => 1, 'reason' => 'Customer confirmed by phone.'])->assertRedirect();
        $this->post(route('admin.customers.notes.store', $customer), ['note' => 'Call after gallery release.', 'tags' => 'VIP, follow-up', 'follow_up_at' => now()->addDay()->format('Y-m-d H:i:s')])->assertRedirect();
        $this->post(route('admin.customers.risk-events.store', $customer), ['type' => 'chargeback', 'order_id' => $order->id, 'amount' => '20.00', 'details' => 'Provider notice received.'])->assertRedirect();
        $this->post(route('admin.customers.privacy.store', $customer), ['action' => 'export', 'reason' => 'Customer access request.'])->assertOk()->assertDownload();
        $this->assertTrue(CustomerConsentHistory::where('user_id', $customer->id)->where('channel', 'marketing_email')->where('granted', true)->exists());
        $this->assertSame(['VIP', 'follow-up'], CustomerAccountNote::where('user_id', $customer->id)->sole()->tags);
        $this->assertSame(2000, CustomerRiskEvent::where('user_id', $customer->id)->sole()->amount);
        $this->assertDatabaseHas('customer_privacy_requests', ['user_id' => $customer->id, 'type' => 'export', 'status' => 'completed']);
    }

    public function test_duplicate_accounts_can_be_merged_into_a_confirmed_primary_account(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->customer()->create(['email' => 'primary@example.test']);
        $source = User::factory()->customer()->create(['email' => 'duplicate@example.test']);
        Order::factory()->paid()->create(['user_id' => $source->id]);
        $this->actingAs($admin)->post(route('admin.customers.merge.store'), ['source_customer_id' => $source->id, 'target_customer_id' => $target->id, 'confirmation_email' => $target->email, 'reason' => 'Same customer confirmed both accounts.'])->assertRedirect(route('admin.customers.show', $target));
        $this->assertSame($target->id, $source->fresh()->merged_into_user_id);
        $this->assertFalse($source->fresh()->is_active);
        $this->assertSame($target->id, Order::firstOrFail()->user_id);
        $this->assertDatabaseHas('customer_account_merges', ['source_user_id' => $source->id, 'target_user_id' => $target->id]);
    }

    public function test_anonymisation_requires_the_current_email_and_retains_orders(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->customer()->create(['email' => 'privacy@example.test']);
        $order = Order::factory()->paid()->create(['user_id' => $customer->id]);
        $this->actingAs($admin)->post(route('admin.customers.privacy.store', $customer), ['action' => 'anonymize', 'reason' => 'Retention period ended.', 'confirmation_email' => 'wrong@example.test'])->assertSessionHasErrors('confirmation_email');
        $this->post(route('admin.customers.privacy.store', $customer), ['action' => 'anonymize', 'reason' => 'Retention period ended.', 'confirmation_email' => 'privacy@example.test'])->assertRedirect();
        $this->assertNotNull($customer->fresh()->anonymized_at);
        $this->assertFalse($customer->fresh()->is_active);
        $this->assertDatabaseHas('orders',['id' => $order->id, 'user_id' => $customer->id]);
    }
}
