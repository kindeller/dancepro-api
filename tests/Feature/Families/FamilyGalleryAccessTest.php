<?php

namespace Tests\Feature\Families;

use App\Features\Concerts\Models\Concert;
use App\Features\Concerts\Models\ConcertAccessGrant;
use App\Features\Customers\Models\CustomerCompetitionRegistration;
use App\Features\Customers\Models\CustomerStudio;
use App\Features\Customers\Services\CustomerCompetitionAccess;
use App\Features\Customers\Services\CustomerConcertAccess;
use App\Features\Customers\Services\CustomerConcertGallery;
use App\Features\Customers\Services\CustomerMediaProfile;
use App\Features\Customers\Services\StudioCommunicationHistory;
use App\Features\Families\Services\CustomerFamilies;
use App\Features\Families\Services\FamilyGalleryAccess;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use App\Features\Studios\Services\StudioMemberships;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FamilyGalleryAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_studio_membership_shares_released_concert_photos_but_not_video_access(): void
    {
        [$manager, $member, $user] = $this->family();
        app(CustomerFamilies::class)->accept($user, $member, 'test-token');
        $studio = Studio::factory()->create(['status' => 'active']);
        app(StudioMemberships::class)->add($manager, $studio->uuid, $studio->code);
        $concert = Concert::factory()->published()->create(['studio_id' => $studio->id]);
        $this->assertTrue(app(CustomerConcertGallery::class)->allows($user, $concert));
        $this->assertFalse(app(CustomerConcertAccess::class)->allows($user, $concert));
        $unreleased = Concert::factory()->awaitingApproval()->create(['studio_id' => $studio->id]);
        $this->assertFalse(app(CustomerConcertGallery::class)->allows($user, $unreleased));
        $this->assertTrue(app(FamilyGalleryAccess::class)->studios($user)->where('studio_id', $studio->id)->exists());
        $this->assertSame([$studio->id], app(CustomerMediaProfile::class)->forUser($user)['studios']->pluck('id')->all());
        $this->actingAs($user)->get(route('customer.profile.edit', ['tab' => 'studios']))->assertOk()->assertSee($studio->name)->assertSee('Added through your family account manager.');
        $this->get(route('customer.profile.studio', $studio))->assertOk();
    }

    public function test_acceptance_inherits_current_and_later_studios_and_events_without_duplicate_records(): void
    {
        [$manager, $member, $user] = $this->family();
        $studio = Studio::factory()->create(['status' => 'active']);
        $this->studio($manager, $studio);
        $event = $this->competition($manager);
        $concert = Concert::factory()->published()->create(['studio_id' => $studio->id]);
        $this->grant($manager, $concert);
        $access = app(FamilyGalleryAccess::class);
        $this->assertFalse(app(CustomerCompetitionAccess::class)->allows($user, $event));
        $this->assertFalse(app(CustomerConcertAccess::class)->allows($user, $concert));
        $this->assertCount(0, $access->studios($user)->get());
        app(CustomerFamilies::class)->accept($user, $member, 'test-token');
        $this->assertTrue(app(CustomerCompetitionAccess::class)->allows($user, $event));
        $this->assertTrue(app(CustomerConcertAccess::class)->allows($user, $concert));
        $this->assertTrue(app(CustomerConcertGallery::class)->allows($user, $concert));
        $this->assertSame([$studio->id], app(CustomerMediaProfile::class)->forUser($user)['studios']->pluck('id')->all());
        $this->actingAs($user)->get(route('customer.media.index'))->assertOk()->assertSee($event->name)->assertSee($concert->name);
        $this->get(route('customer.profile.edit', ['tab' => 'studios']))->assertOk()->assertSee($studio->name)->assertSee('Added through your family account manager.')
            ->assertDontSee('Remove '.$studio->name);
        $secondStudio = Studio::factory()->create(['status' => 'active']);
        $this->studio($manager, $secondStudio);
        $later = $this->competition($manager);
        $this->assertTrue($access->studios($user)->where('studio_id', $secondStudio->id)->exists());
        $this->assertTrue(app(CustomerCompetitionAccess::class)->allows($user, $later));
        CustomerCompetitionRegistration::create(['user_id' => $user->id, 'scheduling_event_id' => $event->id, 'access_verified_at' => now()]);
        $this->assertSame(1, $access->competitions($user)->where('scheduling_event_id', $event->id)->count());
        $this->assertDatabaseMissing('customer_studios', ['user_id' => $user->id, 'studio_id' => $studio->id]);
        $this->assertDatabaseMissing('concert_access_grants', ['user_id' => $user->id, 'concert_id' => $concert->id]);
        $this->assertFalse(app(StudioCommunicationHistory::class)->canView($user, $studio));
    }

    public function test_leaving_removes_inherited_access_but_preserves_independently_added_studios_and_events(): void
    {
        [$manager, $member, $user] = $this->family();
        app(CustomerFamilies::class)->accept($user, $member, 'test-token');
        $studio = Studio::factory()->create(['status' => 'active']);
        $this->studio($manager, $studio);
        $shared = $this->competition($manager);
        $own = $this->competition($user);
        $ownStudio = Studio::factory()->create(['status' => 'active']);
        $this->studio($user, $ownStudio);
        $concert = Concert::factory()->published()->create();
        $this->grant($manager, $concert);
        app(CustomerFamilies::class)->change($user, ['action' => 'leave']);
        $this->assertFalse(app(CustomerCompetitionAccess::class)->allows($user, $shared));
        $this->assertTrue(app(CustomerCompetitionAccess::class)->allows($user, $own));
        $this->assertFalse(app(CustomerConcertAccess::class)->allows($user, $concert));
        $this->assertSame([$ownStudio->id], app(CustomerMediaProfile::class)->forUser($user)['studios']->pluck('id')->all());
    }

    public function test_inherited_access_preserves_verification_release_expiry_and_revocation_rules(): void
    {
        [$manager, $member, $user] = $this->family();
        app(CustomerFamilies::class)->accept($user, $member, 'test-token');
        $event = $this->competition($manager);
        $concert = Concert::factory()->awaitingApproval()->create();
        $grant = $this->grant($manager, $concert);
        $access = app(CustomerConcertAccess::class);
        $this->assertFalse($access->allows($user, $concert));
        $concert->update(['requires_approval' => false]);
        $this->assertTrue($access->allows($user, $concert));
        $grant->update(['expires_at' => now()->subSecond()]);
        $this->assertFalse($access->allows($user, $concert));
        $grant->update(['expires_at' => null, 'revoked_at' => now()]);
        $this->assertFalse($access->allows($user, $concert));
        CustomerCompetitionRegistration::where('user_id', $manager->id)->update(['access_verified_at' => null]);
        $this->assertFalse(app(CustomerCompetitionAccess::class)->allows($user, $event));
        $studio = Studio::factory()->create(['status' => 'active']);
        CustomerStudio::create(['user_id' => $manager->id, 'studio_id' => $studio->id]);
        $this->assertFalse(app(FamilyGalleryAccess::class)->studios($user)->exists());
        $this->studio($manager, $studio);
        $manager->update(['is_active' => false]);
        $this->assertFalse(app(FamilyGalleryAccess::class)->studios($user)->exists());
        $this->assertFalse(app(FamilyGalleryAccess::class)->studios(User::factory()->customer()->create())->exists());
    }

    public function test_release_notification_recipients_include_accepted_members_but_not_old_or_unaccepted_invitations(): void
    {
        [$manager, $member, $user] = $this->family();
        $studio = Studio::factory()->create(['status' => 'active']);
        $this->studio($manager, $studio);
        $beforeAcceptance = now();
        $access = app(FamilyGalleryAccess::class);
        $this->assertFalse($access->studioViewers($studio->id)->whereKey($user->id)->exists());
        $this->travel(1)->minutes();
        app(CustomerFamilies::class)->accept($user, $member, 'test-token');
        $this->assertTrue($access->studioViewers($studio->id)->whereKey($user->id)->exists());
        $this->assertFalse($access->studioViewers($studio->id, $beforeAcceptance)->whereKey($user->id)->exists());
        app(CustomerFamilies::class)->change($manager, ['action' => 'remove', 'member_uuid' => $member->uuid]);
        $this->assertFalse($access->studioViewers($studio->id)->whereKey($user->id)->exists());
    }

    private function family(): array
    {
        $manager = User::factory()->customer()->create();
        $user = User::factory()->customer()->create();
        $family = app(CustomerFamilies::class)->ensure($manager);
        $member = $family->members()->create(['first_name' => 'Sam', 'last_name' => 'Dancer', 'email' => $user->email,
            'status' => 'pending', 'can_view_media' => false, 'can_purchase' => true,
            'invitation_hash' => hash('sha256', 'test-token'), 'expires_at' => now()->addDays(14)]);

        return [$manager, $member, $user];
    }

    private function studio(User $user, Studio $studio): void
    {
        CustomerStudio::updateOrCreate(['user_id' => $user->id, 'studio_id' => $studio->id], ['access_verified_at' => now()]);
    }

    private function competition(User $user): SchedulingEvent
    {
        $event = SchedulingEvent::create(['name' => 'Family competition '.fake()->uuid(), 'event_type' => 'competition', 'event_date' => now()]);
        CustomerCompetitionRegistration::create(['user_id' => $user->id, 'scheduling_event_id' => $event->id, 'access_verified_at' => now()]);

        return $event;
    }

    private function grant(User $user, Concert $concert): ConcertAccessGrant
    {
        return ConcertAccessGrant::create(['user_id' => $user->id, 'concert_id' => $concert->id, 'source' => 'admin_support', 'status' => 'active']);
    }
}
