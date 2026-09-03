<?php

namespace Tests\Feature\Crew;

use App\Features\Crew\Actions\RecordCrewContractSignature;
use App\Features\Crew\Models\CrewContract;
use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Models\CrewRole;
use App\Features\Crew\Support\ContractSignatureRecordingMethod;
use App\Features\Crew\Support\CrewContractSignatureStatus;
use App\Features\Crew\Support\CrewContractStatus;
use App\Features\Crew\Support\CrewRoleQualificationStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CrewFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_crew_profile_stores_clothing_sizes_and_calculates_service_from_commencement_date(): void
    {
        $profile = CrewProfile::factory()->create([
            'shirt_size' => 'Ladies 10',
            'jacket_size' => 'M',
            'commencement_date' => '2020-02-29',
        ]);

        $asOf = Carbon::parse('2026-08-29');

        $this->assertSame('Ladies 10', $profile->shirt_size);
        $this->assertSame('M', $profile->jacket_size);
        $this->assertSame(78, $profile->monthsOfService($asOf));
        $this->assertSame(6, $profile->completedYearsOfService($asOf));
    }

    public function test_service_length_is_unknown_without_a_commencement_date(): void
    {
        $profile = CrewProfile::factory()->create(['commencement_date' => null]);

        $this->assertNull($profile->monthsOfService());
        $this->assertNull($profile->completedYearsOfService());
    }

    public function test_crew_roles_have_an_explicit_qualification_status(): void
    {
        $profile = CrewProfile::factory()->create();
        $role = CrewRole::factory()->create([
            'code' => 'photographer_p2',
            'name' => 'Photographer P2',
        ]);

        $profile->roles()->attach($role, [
            'status' => CrewRoleQualificationStatus::Training,
            'effective_from' => '2026-08-01',
        ]);

        $qualification = $profile->roleQualifications()->firstOrFail();

        $this->assertSame(CrewRoleQualificationStatus::Training, $qualification->status);
        $this->assertTrue($qualification->effective_from->isSameDay('2026-08-01'));
    }

    public function test_existing_contract_signature_can_be_recorded_and_corrected_without_losing_history(): void
    {
        Carbon::setTestNow('2026-08-29 10:00:00');

        $profile = CrewProfile::factory()->create();
        $contract = CrewContract::factory()->create();
        $administrator = User::factory()->create(['type' => 'admin']);
        $action = app(RecordCrewContractSignature::class);

        $signature = $action->execute(
            crewProfile: $profile,
            contract: $contract,
            signedAt: Carbon::parse('2024-03-12 09:30:00'),
            recordedBy: $administrator,
            note: 'Entered from the existing paper contract.',
        );

        $this->assertSame(CrewContractSignatureStatus::Signed, $signature->status);
        $this->assertSame(ContractSignatureRecordingMethod::ManualExisting, $signature->recording_method);
        $this->assertTrue($signature->signed_at->equalTo('2024-03-12 09:30:00'));
        $this->assertCount(1, $signature->events);

        $corrected = $action->execute(
            crewProfile: $profile,
            contract: $contract,
            signedAt: Carbon::parse('2024-03-10 09:30:00'),
            recordedBy: $administrator,
            note: 'Corrected after checking the original contract.',
        );

        $this->assertSame($signature->id, $corrected->id);
        $this->assertSame(ContractSignatureRecordingMethod::ManualCorrection, $corrected->recording_method);
        $this->assertTrue($corrected->signed_at->equalTo('2024-03-10 09:30:00'));
        $this->assertCount(2, $corrected->events);

        $correctionEvent = $corrected->events()->latest('id')->firstOrFail();
        $this->assertTrue($correctionEvent->previous_signed_at->equalTo('2024-03-12 09:30:00'));
        $this->assertTrue($correctionEvent->new_signed_at->equalTo('2024-03-10 09:30:00'));
        $this->assertSame(ContractSignatureRecordingMethod::ManualCorrection, $correctionEvent->recording_method);
        $this->assertSame($administrator->id, $correctionEvent->recorded_by_user_id);
    }

    public function test_crew_member_can_update_their_safe_profile_fields_without_changing_admin_only_data(): void
    {
        $user = User::factory()->crew()->create(['email' => 'crew.profile@dancepro.test']);
        $profile = CrewProfile::factory()->for($user)->create([
            'preferred_name' => 'Alex',
            'phone' => '0400 000 000',
            'internal_notes' => 'Private administrator note.',
            'owned_equipment' => 'Admin-managed camera list.',
            'usual_travel_area' => 'Perth metro',
            'super_fund_name' => 'Existing Super Fund',
            'super_member_number' => 'MEMBER-123',
        ]);
        CrewContract::factory()->create(['status' => CrewContractStatus::Active, 'name' => 'Current Crew Agreement']);

        $this->actingAs($user)->get(route('crew.profile.edit'))
            ->assertOk()
            ->assertSee('My Profile')
            ->assertSee('Change password')
            ->assertSee('password-dialog', false)
            ->assertSee('Log out')
            ->assertSee('Current Crew Agreement')
            ->assertSee('Working With Children Check')
            ->assertDontSee('Private administrator note.')
            ->assertDontSee('Admin-managed camera list.')
            ->assertDontSee('Super fund')
            ->assertDontSee('Usual travel area');

        $this->actingAs($user)->put(route('crew.profile.update'), [
            'preferred_name' => 'Alex Updated',
            'legal_name' => 'Alex Example',
            'email' => 'alex.updated@dancepro.test',
            'phone' => '0412 345 678',
            'address_line_1' => '10 Example Street',
            'suburb' => 'Perth',
            'state' => 'WA',
            'postcode' => '6000',
            'shirt_size' => 'M',
            'jacket_size' => 'L',
            'working_with_children_number' => 'WWC123456',
            'working_with_children_expiry' => today()->addYear()->toDateString(),
            'vehicles' => [['make' => 'Toyota', 'model' => 'RAV4', 'registration' => '1ABC234', 'colour' => 'Blue']],
            'internal_notes' => 'Crew must not overwrite this.',
            'owned_equipment' => 'Crew must not overwrite this either.',
            'usual_travel_area' => 'Everywhere',
            'super_fund_name' => 'Replacement Fund',
            'super_member_number' => 'REPLACEMENT-456',
        ])->assertRedirect()->assertSessionHas('status');

        $profile->refresh();
        $this->assertSame('Alex Updated', $profile->preferred_name);
        $this->assertSame('alex.updated@dancepro.test', $user->refresh()->email);
        $this->assertSame('WWC123456', $profile->working_with_children_number);
        $this->assertSame('Private administrator note.', $profile->internal_notes);
        $this->assertSame('Admin-managed camera list.', $profile->owned_equipment);
        $this->assertSame('Perth metro', $profile->usual_travel_area);
        $this->assertSame('Existing Super Fund', $profile->super_fund_name);
        $this->assertSame('MEMBER-123', $profile->super_member_number);
        $this->assertNotNull($user->refresh()->onboarding_completed_at);
        $this->assertDatabaseHas('crew_vehicles', ['crew_profile_id' => $profile->id, 'make' => 'Toyota', 'registration' => '1ABC234']);
    }

    public function test_phone_and_address_are_required_to_complete_a_crew_profile(): void
    {
        $user = User::factory()->crew()->create(['onboarding_completed_at' => null]);
        CrewProfile::factory()->for($user)->create();

        $this->actingAs($user)->put(route('crew.profile.update'), [
            'preferred_name' => 'Alex',
            'email' => $user->email,
        ])->assertSessionHasErrors(['phone', 'address_line_1', 'suburb', 'state', 'postcode', 'working_with_children_number', 'working_with_children_expiry']);

        $this->assertNull($user->refresh()->onboarding_completed_at);
    }

    public function test_crew_profile_shows_australian_address_search_when_google_maps_is_configured(): void
    {
        config()->set('services.google_maps.browser_key', 'test-browser-key');
        $user = User::factory()->crew()->create();
        CrewProfile::factory()->for($user)->create();

        $this->actingAs($user)->get(route('crew.profile.edit'))
            ->assertOk()
            ->assertSee('Find your address')
            ->assertSee("autocomplete.includedRegionCodes = ['au']", false)
            ->assertSee('test-browser-key', false);
    }

    public function test_crew_member_must_confirm_their_current_password_before_changing_it(): void
    {
        $user = User::factory()->crew()->create(['password' => 'current-password']);
        CrewProfile::factory()->for($user)->create();
        $tokenName = 'existing-device';
        $user->createToken($tokenName);
        $rememberToken = $user->remember_token;

        $this->actingAs($user)->put(route('crew.profile.password'), [
            'current_password' => 'incorrect-password',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('current-password', $user->refresh()->password));

        $this->actingAs($user)->put(route('crew.profile.password'), [
            'current_password' => 'current-password',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertRedirect()->assertSessionHas('status');
        $this->assertTrue(Hash::check('new-secure-password', $user->refresh()->password));
        $this->assertNotSame($rememberToken, $user->remember_token);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => $tokenName,
        ]);
        $this->get(route('crew.profile.edit'))->assertOk();
    }
}
