<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminExceptionOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_view_the_exception_overview_tabs(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)
            ->get(route('admin.exceptions.index'))
            ->assertOk()
            ->assertSee('Exceptions')
            ->assertSee('Shifts &amp; Events', false)
            ->assertSee('Timekeeping')
            ->assertSee('Payments')
            ->assertSee('Communication')
            ->assertSee('Nothing needs attention');
    }

    public function test_staff_can_view_the_hub_management_dashboard(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)
            ->get(route('admin.hub.dashboard'))
            ->assertOk()
            ->assertSee('Events in next 14 days')
            ->assertSee('Published crew assignments')
            ->assertSee('Open cover requests')
            ->assertSee('Pending invoices')
            ->assertSee('Upcoming Events')
            ->assertSee('Needs Attention');
    }

    public function test_customer_cannot_view_the_exception_overview(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer)
            ->get(route('admin.exceptions.index'))
            ->assertForbidden();
    }

    public function test_customer_cannot_view_the_hub_management_dashboard(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer)
            ->get(route('admin.hub.dashboard'))
            ->assertForbidden();
    }
}
