<?php

namespace Tests\Feature\Admin;

use App\Features\Crew\Models\CrewProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminAccessBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_staff_and_admin_users_can_access_admin_routes(): void
    {
        foreach ([User::factory()->staff()->create(), User::factory()->admin()->create()] as $user) {
            $this->actingAs($user)
                ->get('/admin')
                ->assertOk();
        }
    }

    public function test_customer_and_crew_users_cannot_access_admin_routes(): void
    {
        foreach ([User::factory()->customer()->create(), User::factory()->crew()->create()] as $user) {
            $this->actingAs($user)
                ->get('/admin')
                ->assertForbidden();
        }
    }

    public function test_inactive_staff_and_admin_users_cannot_access_admin_routes(): void
    {
        foreach ([User::factory()->staff()->inactive()->create(), User::factory()->admin()->inactive()->create()] as $user) {
            $this->actingAs($user)
                ->get('/admin')
                ->assertForbidden();
        }
    }

    public function test_every_admin_route_uses_the_staff_access_boundary(): void
    {
        $adminRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => $route->uri() === 'admin' || str_starts_with($route->uri(), 'admin/'));

        $this->assertNotEmpty($adminRoutes);

        $adminRoutes->each(fn ($route) => $this->assertContains(
            'admin.required',
            $route->gatherMiddleware(),
            "Admin route [{$route->uri()}] is missing the staff access boundary.",
        ));
    }

    public function test_active_crew_with_a_profile_can_access_crew_routes(): void
    {
        $crewUser = User::factory()->crew()->create();
        CrewProfile::factory()->for($crewUser)->create();

        $this->actingAs($crewUser)
            ->get('/crew/directory')
            ->assertOk();
    }

    public function test_inactive_crew_and_non_crew_accounts_cannot_access_crew_routes(): void
    {
        $inactiveCrew = User::factory()->crew()->inactive()->create();
        CrewProfile::factory()->for($inactiveCrew)->create();
        $customer = User::factory()->customer()->create();
        CrewProfile::factory()->for($customer)->create();
        $admin = User::factory()->admin()->create();
        CrewProfile::factory()->for($admin)->create();

        foreach ([$inactiveCrew, $customer, $admin] as $user) {
            $this->actingAs($user)
                ->get('/crew/directory')
                ->assertForbidden();
        }

        $this->actingAs(User::factory()->crew()->create())
            ->get('/crew/directory')
            ->assertForbidden();
    }

    public function test_every_crew_route_uses_the_crew_access_boundary(): void
    {
        $crewRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => str_starts_with($route->uri(), 'crew/'));

        $this->assertNotEmpty($crewRoutes);

        $crewRoutes->each(fn ($route) => $this->assertContains(
            'crew.required',
            $route->gatherMiddleware(),
            "Crew route [{$route->uri()}] is missing the crew access boundary.",
        ));
    }
}
