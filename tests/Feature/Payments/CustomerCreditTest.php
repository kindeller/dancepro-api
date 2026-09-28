<?php

namespace Tests\Feature\Payments;

use App\Features\Orders\Models\Order;
use App\Features\Payments\Models\CustomerCreditTransaction;
use App\Features\Payments\Services\CustomerCredits;
use App\Features\Payments\Services\ProcessTestPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerCreditTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_find_a_customer_by_email_or_account_id_and_add_credit(): void
    {
        $staff = User::factory()->staff()->create();
        $customer = User::factory()->customer()->create([
            'name' => 'Credit Customer',
            'email' => 'credit-customer@example.test',
        ]);

        $this->actingAs($staff)->get(route('admin.media-settings.index', [
            'tab' => 'discounts',
            'customer_lookup' => 'credit-customer@example.test',
        ]))->assertOk()
            ->assertSee('Customer account credit')
            ->assertSee('Account #'.$customer->id)
            ->assertSee('credit-customer@example.test');

        $this->get(route('admin.media-settings.index', [
            'tab' => 'discounts',
            'customer_lookup' => (string) $customer->id,
        ]))->assertOk()->assertSee('Account #'.$customer->id);

        $this->post(route('admin.media-settings.customer-credits.store'), [
            'user_id' => $customer->id,
            'amount' => '25.50',
            'credit_type' => 'gift',
            'note' => 'Goodwill credit for delayed delivery',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('customer_credit_transactions', [
            'user_id' => $customer->id,
            'amount' => 2550,
            'kind' => 'gift_credit',
            'note' => 'Goodwill credit for delayed delivery',
            'created_by_user_id' => $staff->id,
        ]);
        $this->assertSame(2550, app(CustomerCredits::class)->balance($customer));

        $this->actingAs($customer)->get(route('customer.media.index'))
            ->assertOk()
            ->assertSee('Account credit')
            ->assertSee('$25.50');
    }

    public function test_an_approved_payment_redeems_credit_once_and_a_decline_does_not_redeem_it(): void
    {
        config(['payments.driver' => 'fake']);
        $staff = User::factory()->staff()->create();
        $customer = User::factory()->customer()->create();
        app(CustomerCredits::class)->grant($customer, 5000, $staff, 'Account credit');

        $declined = Order::factory()->create([
            'user_id' => $customer->id,
            'total_amount' => 1000,
            'metadata' => ['source' => 'factory', 'account_credit_amount' => 1000, 'payment_amount' => 1000],
        ]);
        app(ProcessTestPayment::class)->execute($customer, $declined, (string) Str::uuid(), 'declined');
        $this->assertSame(5000, app(CustomerCredits::class)->balance($customer));

        $approved = Order::factory()->create([
            'user_id' => $customer->id,
            'total_amount' => 2000,
            'metadata' => ['source' => 'factory', 'account_credit_amount' => 3000, 'payment_amount' => 2000],
        ]);
        $key = (string) Str::uuid();
        app(ProcessTestPayment::class)->execute($customer, $approved, $key, 'approved');
        app(ProcessTestPayment::class)->execute($customer, $approved, $key, 'approved');

        $this->assertSame(2000, app(CustomerCredits::class)->balance($customer));
        $this->assertDatabaseHas('payment_attempts', ['order_id' => $approved->id, 'amount' => 2000]);
        $this->assertSame(1, CustomerCreditTransaction::query()->where('order_id', $approved->id)->count());
        $this->assertDatabaseHas('customer_credit_transactions', [
            'order_id' => $approved->id,
            'user_id' => $customer->id,
            'amount' => -3000,
            'kind' => 'order_redemption',
        ]);
    }

    public function test_staff_cannot_add_credit_to_a_non_customer_account(): void
    {
        $this->actingAs(User::factory()->staff()->create());
        $otherStaff = User::factory()->staff()->create();

        $this->post(route('admin.media-settings.customer-credits.store'), [
            'user_id' => $otherStaff->id,
            'amount' => '20.00',
            'credit_type' => 'reward',
            'note' => 'Invalid target',
        ])->assertSessionHasErrors('user_id');

        $this->assertDatabaseCount('customer_credit_transactions', 0);
    }

    public function test_sales_reporting_keeps_discounts_and_account_credit_separate(): void
    {
        $staff = User::factory()->staff()->create();
        $customer = User::factory()->customer()->create();
        Order::factory()->paid()->create([
            'user_id' => $customer->id,
            'subtotal_amount' => 10000,
            'total_amount' => 6500,
            'tax_amount' => 591,
            'metadata' => [
                'source' => 'customer_cart',
                'discount_amount' => 1000,
                'account_credit_amount' => 2500,
                'payment_amount' => 6500,
            ],
        ]);

        $this->actingAs($staff)->get(route('admin.media-sales.index'))
            ->assertOk()
            ->assertSee('Cash collected')
            ->assertSee('$65.00')
            ->assertSee('Discounts: $10.00')
            ->assertSee('Account credit used: $25.00');
    }
}
