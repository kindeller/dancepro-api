<?php

namespace Tests\Feature\Crew;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Customers\Support\UserType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class CrewInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_invited_crew_member_sets_a_password_and_is_taken_to_their_profile(): void
    {
        $user = User::factory()->create([
            'type' => UserType::Crew->value,
            'email' => 'new.crew@dancepro.test',
            'invitation_sent_at' => now(),
            'onboarding_completed_at' => null,
        ]);
        CrewProfile::factory()->for($user)->create(['preferred_name' => 'New Crew']);
        $token = Password::broker()->createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
            'onboarding' => '1',
        ])->assertRedirect(route('crew.profile.edit'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->refresh()->email_verified_at);
        $this->assertNull($user->onboarding_completed_at);
    }

    public function test_admin_crew_list_shows_onboarding_state(): void
    {
        $staff = User::factory()->staff()->create();
        $invited = CrewProfile::factory()->create(['preferred_name' => 'Invited Person']);
        $invited->user->update(['invitation_sent_at' => now()]);
        $complete = CrewProfile::factory()->create(['preferred_name' => 'Complete Person']);
        $complete->user->update(['invitation_sent_at' => now(), 'onboarding_completed_at' => now()]);

        $this->actingAs($staff)->get(route('admin.crew.index'))
            ->assertOk()
            ->assertSee('Invite sent')
            ->assertSee('Awaiting setup')
            ->assertSee('Complete')
            ->assertSee('Resend invite');
    }
}
