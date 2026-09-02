<?php

namespace Tests\Feature\Admin;

use App\Features\Crew\Models\CrewContract;
use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Models\CrewRole;
use App\Features\Crew\Notifications\CrewInvitation;
use App\Features\Customers\Support\UserType;
use App\Features\Scheduling\Models\EventTypeDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminCrewManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_create_and_update_a_crew_member_with_qualifications(): void
    {
        Notification::fake();
        $staff = User::factory()->staff()->create();
        $role = CrewRole::factory()->create(['name' => 'Videographer']);

        $this->actingAs($staff)->post(route('admin.crew.store'), [
            'preferred_name' => 'Alex',
            'email' => 'alex@example.test',
            'send_invitation' => '1',
        ])->assertRedirect(route('admin.crew.index'));

        $crewProfile = CrewProfile::query()->where('preferred_name', 'Alex')->firstOrFail();
        Notification::assertSentTo($crewProfile->user, CrewInvitation::class);
        $this->assertNotNull($crewProfile->user->invitation_sent_at);

        $this->actingAs($staff)->put(route('admin.crew.update', $crewProfile), [
            'preferred_name' => 'Alex',
            'legal_name' => 'Alex Reed',
            'email' => 'alex@example.test',
            'phone' => '0400 000 001',
            'shirt_size' => 'M',
            'jacket_size' => 'L',
            'commencement_date' => '2020-08-29',
            'date_of_birth' => '1990-05-10',
            'address_line_1' => '1 Example Street',
            'suburb' => 'Perth',
            'state' => 'WA',
            'postcode' => '6000',
            'emergency_contact_name' => 'Taylor Reed',
            'emergency_contact_relationship' => 'Partner',
            'emergency_contact_phone' => '0400 999 999',
            'abn' => '12 345 678 901',
            'bank_bsb' => '123-456',
            'bank_account_number' => '12345678',
            'medical_information' => 'Example allergy.',
            'is_active' => '1',
            'vehicles' => [
                ['make' => 'Toyota', 'model' => 'Corolla', 'registration' => '1ABC234', 'colour' => 'Blue'],
                ['make' => 'Ford', 'model' => 'Transit', 'registration' => 'DPVAN1', 'colour' => 'White'],
            ],
            'qualifications' => [
                $role->id => ['status' => 'training', 'effective_from' => '2026-01-01'],
            ],
        ])->assertRedirect();

        $crewProfile->refresh();
        $this->assertSame('M', $crewProfile->shirt_size);
        $this->assertSame('L', $crewProfile->jacket_size);
        $this->assertSame('alex@example.test', $crewProfile->user->email);
        $this->assertSame('123-456', $crewProfile->bank_bsb);
        $this->assertSame(2, $crewProfile->vehicles()->count());
        $this->assertDatabaseMissing('crew_profiles', ['id' => $crewProfile->id, 'bank_bsb' => '123-456']);
        $this->assertDatabaseHas('crew_role_qualifications', [
            'crew_profile_id' => $crewProfile->id,
            'crew_role_id' => $role->id,
            'status' => 'training',
        ]);
        $this->actingAs($staff)->get(route('admin.crew.edit', $crewProfile))
            ->assertOk()
            ->assertSee('Admin access')
            ->assertSee('Time with DancePro')
            ->assertSee('Role qualifications')
            ->assertSee('Working With Children Check')
            ->assertDontSee('Super fund')
            ->assertDontSee('Profile photo')
            ->assertDontSee("Driver's licence number")
            ->assertDontSee('First-aid qualification');

        $this->actingAs($staff)->put(route('admin.crew.update', $crewProfile), [
            'preferred_name' => 'Alex',
            'legal_name' => 'Alex Reed',
            'email' => 'alex@example.test',
            'phone' => '0400 000 002',
            'shirt_size' => 'S',
            'jacket_size' => 'M',
            'commencement_date' => '2020-08-29',
            'date_of_birth' => '1990-05-10',
            'emergency_contact_phone' => '0400 999 999',
            'bank_bsb' => '123-456',
            'bank_account_number' => '12345678',
            'medical_information' => 'Example allergy.',
            'is_active' => '0',
            'vehicles' => [
                ['uuid' => $crewProfile->vehicles()->first()->uuid, 'make' => 'Toyota', 'model' => 'Corolla', 'registration' => '1ABC234', 'colour' => 'Black'],
            ],
            'qualifications' => [$role->id => ['status' => 'approved']],
        ])->assertRedirect();

        $crewProfile->refresh();
        $this->assertFalse($crewProfile->user->is_active);
        $this->assertSame('approved', $crewProfile->roleQualifications->first()->status->value);
        $this->assertSame(1, $crewProfile->vehicles()->count());
        $this->assertSame('Black', $crewProfile->vehicles()->first()->colour);
    }

    public function test_admin_can_grant_and_remove_full_admin_access_for_a_crew_member(): void
    {
        $admin = User::factory()->admin()->create();
        $crewProfile = CrewProfile::factory()->for(User::factory()->crew())->create([
            'preferred_name' => 'Alex Admin',
            'phone' => '0400 000 001',
            'commencement_date' => '2020-08-29',
        ]);

        $payload = [
            'preferred_name' => 'Alex Admin',
            'email' => $crewProfile->user->email,
            'phone' => '0400 000 001',
            'commencement_date' => '2020-08-29',
            'is_active' => '1',
            'is_admin' => '1',
        ];

        $this->actingAs($admin)
            ->put(route('admin.crew.update', $crewProfile), $payload)
            ->assertRedirect();

        $crewUser = $crewProfile->user->refresh();
        $this->assertTrue($crewUser->is_admin);
        $this->assertTrue($crewUser->canAccessAdmin());
        $this->assertTrue($crewUser->canAccessCrew());

        $this->actingAs($admin)
            ->put(route('admin.crew.update', $crewProfile), [...$payload, 'is_admin' => '0'])
            ->assertRedirect();

        $crewUser->refresh();
        $this->assertFalse($crewUser->is_admin);
        $this->assertTrue($crewUser->canAccessCrew());
    }

    public function test_staff_can_edit_roles_and_update_crew_qualifications_from_the_matrix(): void
    {
        $staff = User::factory()->staff()->create();
        $videoRole = CrewRole::factory()->create(['name' => 'Videographer', 'code' => 'videographer']);
        $photoRole = CrewRole::factory()->create(['name' => 'Photographer', 'code' => 'photographer']);
        $alex = CrewProfile::factory()->for(User::factory()->crew())->create(['preferred_name' => 'Alex']);
        $sam = CrewProfile::factory()->for(User::factory()->crew())->create(['preferred_name' => 'Sam']);
        $alex->roles()->attach($videoRole, ['status' => 'training', 'effective_from' => '2026-01-01']);

        $this->actingAs($staff)->put(route('admin.crew-roles.update', $videoRole), [
            'name' => 'Competition Videographer',
            'code' => 'competition-video-specialist',
            'event_type' => 'competition',
            'is_active' => '1',
        ])->assertRedirect(route('admin.crew-roles.index'));

        $this->assertDatabaseHas('crew_roles', [
            'id' => $videoRole->id,
            'name' => 'Competition Videographer',
            'code' => 'competition-video-specialist',
            'event_type' => 'competition',
        ]);

        $this->actingAs($staff)->get(route('admin.crew-roles.index'))
            ->assertOk()
            ->assertSee('<option value="">Any event</option>', false)
            ->assertSee('Competition')
            ->assertSee('Concert');

        $matrix = [
            'crew_profile_ids' => [$alex->id, $sam->id],
            'crew_role_ids' => [$videoRole->id, $photoRole->id],
            'assignments' => [
                $alex->id => [$videoRole->id => '1'],
                $sam->id => [$photoRole->id => '1'],
            ],
        ];

        $this->actingAs($staff)->put(route('admin.crew-roles.matrix.update'), $matrix)
            ->assertRedirect(route('admin.crew-roles.index'));

        $this->assertDatabaseHas('crew_role_qualifications', [
            'crew_profile_id' => $alex->id,
            'crew_role_id' => $videoRole->id,
            'status' => 'training',
        ]);
        $this->assertDatabaseHas('crew_role_qualifications', [
            'crew_profile_id' => $sam->id,
            'crew_role_id' => $photoRole->id,
            'status' => 'approved',
        ]);

        $matrix['assignments'] = [$sam->id => [$photoRole->id => '1']];
        $this->actingAs($staff)->put(route('admin.crew-roles.matrix.update'), $matrix)
            ->assertRedirect(route('admin.crew-roles.index'));

        $this->assertDatabaseMissing('crew_role_qualifications', [
            'crew_profile_id' => $alex->id,
            'crew_role_id' => $videoRole->id,
        ]);
    }

    public function test_existing_staff_can_be_created_and_have_a_historical_signature_recorded_before_invitation(): void
    {
        Notification::fake();
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->post(route('admin.crew.store'), [
            'preferred_name' => 'Existing Crew',
            'email' => 'existing.crew@dancepro.test',
        ])->assertRedirect(route('admin.crew.index'));

        $profile = CrewProfile::query()->where('preferred_name', 'Existing Crew')->firstOrFail();
        $this->assertNull($profile->user->invitation_sent_at);
        Notification::assertNothingSent();

        $contract = CrewContract::factory()->create();
        $this->actingAs($staff)->post(route('admin.crew.contract-signatures.store', [$profile, $contract]), [
            'signed_at' => '2025-02-01 10:00:00',
            'recording_note' => 'Migrated from the signed paper agreement.',
        ])->assertRedirect();

        $this->assertSame('manual_existing', $profile->contractSignatures()->firstOrFail()->recording_method->value);

        $this->actingAs($staff)->post(route('admin.crew.invite', $profile))->assertRedirect();
        $this->assertNotNull($profile->user->refresh()->invitation_sent_at);
        Notification::assertSentTo($profile->user, CrewInvitation::class);
    }

    public function test_staff_can_create_a_contract_and_record_then_correct_an_existing_signature(): void
    {
        $staff = User::factory()->staff()->create();
        $crewProfile = CrewProfile::factory()->create();

        $this->actingAs($staff)->post(route('admin.crew-contracts.store'), [
            'name' => 'Crew Services Agreement',
            'version' => '2026.1',
            'status' => 'active',
            'effective_from' => '2026-01-01',
            'content' => '<h2 onclick="alert(1)">Example contract</h2><script>alert(1)</script><p>Contract text.</p>',
        ])->assertRedirect(route('admin.crew-contracts.index'));

        $contract = CrewContract::query()->firstOrFail();
        $this->assertStringContainsString('<h2>Example contract</h2>', $contract->content);
        $this->assertStringNotContainsString('onclick', $contract->content);
        $this->assertStringNotContainsString('<script', $contract->content);
        $this->actingAs($staff)->get(route('admin.crew-contracts.index'))
            ->assertOk()
            ->assertSee('Crew Services Agreement')
            ->assertSee(route('admin.crew-contracts.show', $contract), false)
            ->assertSee(route('admin.crew-contracts.duplicate', $contract), false);

        $this->actingAs($staff)->get(route('admin.crew-contracts.show', $contract))
            ->assertOk()
            ->assertSee('Example contract')
            ->assertSee('Contract text.')
            ->assertSee('Duplicate as new version');

        $this->actingAs($staff)->get(route('admin.crew-contracts.duplicate', $contract))
            ->assertOk()
            ->assertSee('Duplicate contract version')
            ->assertSee('value="Crew Services Agreement"', false)
            ->assertSee('Example contract')
            ->assertSee('Enter a new version before saving.')
            ->assertSee('name="version" value=""', false);
        $route = route('admin.crew.contract-signatures.store', [$crewProfile, $contract]);

        $this->actingAs($staff)->post($route, [
            'signed_at' => '2026-02-01 09:30:00',
            'recording_note' => 'Existing staff paperwork imported.',
        ])->assertRedirect();

        $this->actingAs($staff)->post($route, [
            'signed_at' => '2026-02-02 10:00:00',
            'recording_note' => 'Corrected after checking the original.',
        ])->assertRedirect();

        $signature = $crewProfile->contractSignatures()->firstOrFail();
        $this->assertSame('manual_correction', $signature->recording_method->value);
        $this->assertSame(2, $signature->events()->count());
        $this->assertSame('2026-02-01 09:30:00', $signature->events()->oldest()->first()->new_signed_at->format('Y-m-d H:i:s'));
    }

    public function test_customer_cannot_access_crew_administration(): void
    {
        $customer = User::factory()->customer()->create(['type' => UserType::Customer->value]);

        $this->actingAs($customer)->get(route('admin.crew.index'))->assertForbidden();
        $this->actingAs($customer)->get(route('admin.crew-roles.index'))->assertForbidden();
        $this->actingAs($customer)->get(route('admin.crew-contracts.index'))->assertForbidden();
        $this->actingAs($customer)->get(route('admin.crew-management.recognitions-rewards'))->assertForbidden();
        $this->actingAs($customer)->get(route('admin.crew-management.training'))->assertForbidden();
    }

    public function test_navigation_contains_crew_management_link_and_its_tabs(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.crew.index'), false)
            ->assertSee('Crew Management');

        $this->actingAs($staff)
            ->get(route('admin.crew.index'))
            ->assertOk()
            ->assertSee(route('admin.crew-roles.index'), false)
            ->assertSee(route('admin.crew-contracts.index'), false)
            ->assertSee(route('admin.crew-management.recognitions-rewards'), false)
            ->assertSee(route('admin.crew-management.training'), false);
    }

    public function test_staff_can_add_edit_and_inactivate_event_types(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->post(route('admin.event-types.store'), [
            'name' => 'DR Portrait',
            'code' => 'dr-portrait',
            'system_category' => 'concert',
            'description' => 'Portrait-only event workflow.',
            'is_active' => '1',
        ])->assertRedirect();

        $eventType = EventTypeDefinition::query()->where('code', 'dr-portrait')->firstOrFail();
        $this->actingAs($staff)->put(route('admin.event-types.update', $eventType), [
            'name' => 'Dance Recital Portraits',
            'code' => 'dr-portrait',
            'system_category' => 'concert',
            'description' => 'Updated description.',
            'is_active' => '0',
        ])->assertRedirect();

        $this->assertDatabaseHas('event_type_definitions', [
            'id' => $eventType->id,
            'name' => 'Dance Recital Portraits',
            'system_category' => 'concert',
            'is_active' => false,
        ]);
    }
}
