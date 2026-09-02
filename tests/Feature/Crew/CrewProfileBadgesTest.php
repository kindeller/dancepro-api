<?php

namespace Tests\Feature\Crew;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Models\CrewRole;
use App\Features\Crew\Support\CrewRoleQualificationStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CrewProfileBadgesTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_shows_approved_training_and_earned_service_milestones(): void
    {
        Carbon::setTestNow('2026-08-30 12:00:00');
        $user = User::factory()->crew()->create();
        $profile = CrewProfile::factory()->for($user)->create(['commencement_date' => '2023-08-01']);
        $role = CrewRole::factory()->create(['name' => 'Competition Videographer']);
        $profile->roles()->attach($role, [
            'status' => CrewRoleQualificationStatus::Approved,
            'effective_from' => '2024-02-01',
        ]);

        $this->actingAs($user)
            ->get(route('crew.profile.edit'))
            ->assertOk()
            ->assertSee('Training')
            ->assertSee('Competition Videographer')
            ->assertSee('Milestones')
            ->assertSee('1 year with DancePro')
            ->assertSee('3 years with DancePro')
            ->assertDontSee('Recognition')
            ->assertDontSee('Rewards');
    }

    public function test_profile_hides_the_entire_badge_area_when_nothing_has_been_earned(): void
    {
        $user = User::factory()->crew()->create();
        CrewProfile::factory()->for($user)->create(['commencement_date' => null]);

        $this->actingAs($user)
            ->get(route('crew.profile.edit'))
            ->assertOk()
            ->assertDontSee('aria-label="Your achievements"', false)
            ->assertDontSee('aria-label="Training achievements"', false)
            ->assertDontSee('Milestones')
            ->assertDontSee('Recognition')
            ->assertDontSee('Rewards');
    }
}
