<?php

namespace Tests\Feature\Families;

use App\Features\Customers\Models\CustomerDancer;
use App\Features\Families\Services\CustomerFamilies;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class FamilyInvitationOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_invitation_prefills_editable_login_and_signup_without_personal_details_in_urls(): void
    {
        [$member, $url] = $this->invitation();
        $this->get($url)->assertOk()->assertSee('margin-inline:auto', false)
            ->assertDontSee($member->email);
        $this->get(route('login'))->assertOk()
            ->assertViewHas('familyInvitation', fn ($data) => $data['email'] === $member->email)
            ->assertSee('value="invitee@example.test"', false)->assertDontSee('readonly');
        $this->get(route('customer.register'))->assertOk()
            ->assertSee('value="Sam"', false)->assertSee('value="Dancer"', false)
            ->assertSee('value="invitee@example.test"', false)->assertDontSee('readonly');
        $this->withSession(['_old_input' => ['first_name' => 'Samantha', 'last_name' => 'Updated', 'email' => 'corrected@example.test']])
            ->get(route('customer.register'))->assertOk()->assertSee('value="Samantha"', false)
            ->assertSee('value="Updated"', false)->assertSee('value="corrected@example.test"', false);
    }

    public function test_cancelled_expired_or_rotated_invitation_does_not_leave_stale_prefills(): void
    {
        foreach (['cancelled', 'expired', 'rotated'] as $case) {
            [$member, $url] = $this->invitation($case.'@example.test');
            $this->get($url)->assertOk();
            $member->update(match ($case) {
                'cancelled' => ['status' => 'draft', 'invitation_hash' => null],
                'expired' => ['expires_at' => now()->subSecond()],
                'rotated' => ['invitation_hash' => hash('sha256', 'replacement-token')],
            });
            $this->get(route('login'))->assertOk()->assertViewHas('familyInvitation', [])
                ->assertSessionMissing('family_invitation_prefill');
        }
        $this->get(route('customer.register'))->assertOk()->assertViewHas('familyInvitation', []);
    }

    public function test_signup_name_corrections_and_later_profile_changes_keep_the_same_family_link(): void
    {
        Notification::fake();
        [$member, $url] = $this->invitation();
        $dancer = CustomerDancer::create(['user_id' => $member->family->manager_user_id, 'name' => 'Sam Dancer', 'programme_names' => ['S Dancer']]);
        $member->update(['is_dancer' => true, 'customer_dancer_id' => $dancer->id]);
        $uuid = $member->uuid;
        $this->get($url)->assertOk();
        $this->post(route('customer.register.store'), ['first_name' => 'Samantha', 'last_name' => 'Corrected',
            'email' => $member->email, 'password' => 'long-local-password', 'password_confirmation' => 'long-local-password', 'policy_accepted' => 1])
            ->assertSessionHasNoErrors()->assertRedirect(route('customer.verification.notice'));
        $user = User::where('email', $member->email)->firstOrFail();
        $this->assertNull($member->fresh()->user_id);
        $user->markEmailAsVerified();
        $this->actingAs($user)->get(route('customer.verification.notice'))->assertRedirect($url);
        $this->post($url)->assertSessionHasNoErrors()->assertSessionMissing('family_invitation_prefill');
        $this->assertSame($uuid, $member->fresh()->uuid);
        $this->assertSame($user->id, $member->fresh()->user_id);
        $this->assertSame('Samantha', $member->fresh()->first_name);
        $this->assertSame($dancer->id, $member->fresh()->customer_dancer_id);
        $this->post(route('customer.profile.store'), ['action' => 'details', 'first_name' => 'Sammy', 'last_name' => 'Renamed',
            'preferred_name' => 'Sammy', 'phone' => '0400000000', 'email' => $user->email])->assertSessionHasNoErrors();
        $this->post(route('logout'))->assertRedirect(route('login'));
        $page = $this->actingAs($member->family->manager)->get(route('customer.profile.edit', ['tab' => 'family']));
        $this->assertSame(200, $page->status(), (string) $page->headers->get('Location'));
        $page->assertSee('Sammy Renamed')->assertSee('value="Sammy"', false)->assertSee('value="Renamed"', false);
        $this->assertSame($uuid, $member->fresh()->uuid);
        $this->assertSame($user->id, $member->fresh()->user_id);
        $this->assertSame(['S Dancer'], $dancer->fresh()->programme_names);
    }

    public function test_editing_signup_email_does_not_silently_retarget_the_family_invitation(): void
    {
        Notification::fake();
        [$member, $url] = $this->invitation();
        $this->get($url)->assertOk();
        $this->post(route('customer.register.store'), ['first_name' => 'Sam', 'last_name' => 'Dancer',
            'email' => 'different@example.test', 'password' => 'long-local-password', 'password_confirmation' => 'long-local-password', 'policy_accepted' => 1])
            ->assertSessionHasNoErrors();
        $user = User::where('email', 'different@example.test')->firstOrFail();
        $user->markEmailAsVerified();
        $this->actingAs($user)->post($url)->assertSessionHasErrors('family');
        $this->assertNull($member->fresh()->user_id);
        $this->assertSame('invitee@example.test', $member->fresh()->email);
    }

    public function test_signup_and_family_member_forms_require_a_last_name(): void
    {
        Notification::fake();
        $this->post(route('customer.register.store'), ['first_name' => 'Sam', 'last_name' => '',
            'email' => 'new@example.test', 'password' => 'long-local-password', 'password_confirmation' => 'long-local-password', 'policy_accepted' => 1])
            ->assertSessionHasErrors('last_name');
        $this->assertDatabaseMissing('users', ['email' => 'new@example.test']);
        $manager = User::factory()->customer()->create();
        $this->actingAs($manager)->post(route('customer.family.store'), ['action' => 'save', 'first_name' => 'Sam', 'last_name' => '', 'is_dancer' => 1])
            ->assertSessionHasErrors('last_name');
    }

    private function invitation(string $email = 'invitee@example.test'): array
    {
        $manager = User::factory()->customer()->create();
        $family = app(CustomerFamilies::class)->ensure($manager);
        $token = 'valid-invitation-token';
        $member = $family->members()->create(['first_name' => 'Sam', 'last_name' => 'Dancer', 'email' => $email,
            'status' => 'pending', 'can_view_media' => true, 'can_purchase' => true,
            'invitation_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(14)]);

        return [$member, route('customer.family.invitation', ['member' => $member, 'token' => $token])];
    }
}
