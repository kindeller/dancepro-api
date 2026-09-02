<?php

namespace Tests\Feature\Crew;

use App\Features\Crew\Actions\SignCrewContract;
use App\Features\Crew\Models\CrewContract;
use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Support\CrewContractStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrewContractSigningTest extends TestCase
{
    use RefreshDatabase;

    public function test_crew_member_can_review_and_securely_sign_an_active_contract(): void
    {
        $user = User::factory()->crew()->create(['password' => 'current-password', 'onboarding_completed_at' => null]);
        $profile = CrewProfile::factory()->for($user)->create([
            'phone' => '0412 345 678',
            'address_line_1' => '10 Example Street',
            'suburb' => 'Perth',
            'state' => 'WA',
            'postcode' => '6000',
            'working_with_children_number' => 'WWC123',
            'working_with_children_expiry' => now()->addYear(),
        ]);
        $contract = CrewContract::factory()->create([
            'status' => CrewContractStatus::Active,
            'content' => '<h2>Crew agreement</h2><p>Example terms.</p>',
            'document_checksum' => hash('sha256', '<h2>Crew agreement</h2><p>Example terms.</p>'),
        ]);

        $this->actingAs($user)->get(route('crew.contracts.show', $contract))
            ->assertOk()
            ->assertSee('Crew agreement')
            ->assertSee('Sign this contract');

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10', 'HTTP_USER_AGENT' => 'DancePro Test Browser'])
            ->actingAs($user)->post(route('crew.contracts.sign', $contract), [
                'signed_name' => 'Alex Example',
                'accept_contract' => '1',
                'password' => 'current-password',
            ])->assertRedirect(route('crew.profile.edit'));

        $signature = $profile->contractSignatures()->firstOrFail();
        $this->assertSame('digital', $signature->recording_method->value);
        $this->assertSame('Alex Example', $signature->signed_name);
        $this->assertSame(SignCrewContract::CONSENT_TEXT, $signature->consent_text);
        $this->assertSame($contract->document_checksum, $signature->contract_checksum);
        $this->assertSame('DancePro Test Browser', $signature->signer_user_agent);
        $this->assertCount(1, $signature->events);
        $this->assertNotNull($user->refresh()->onboarding_completed_at);
    }

    public function test_signing_requires_the_correct_password_and_explicit_acceptance(): void
    {
        $user = User::factory()->crew()->create(['password' => 'current-password']);
        CrewProfile::factory()->for($user)->create();
        $contract = CrewContract::factory()->create(['status' => CrewContractStatus::Active]);

        $this->actingAs($user)->post(route('crew.contracts.sign', $contract), [
            'signed_name' => 'Alex Example',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors(['accept_contract', 'password']);

        $this->assertDatabaseCount('crew_contract_signatures', 0);
    }
}
