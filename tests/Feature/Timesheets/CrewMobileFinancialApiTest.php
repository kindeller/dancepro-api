<?php

namespace Tests\Feature\Timesheets;

use App\Features\Auth\Support\TokenAbility;
use App\Features\Crew\Models\CrewContract;
use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Models\CrewRole;
use App\Features\Crew\Support\CrewContractStatus;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Timesheets\Models\CrewInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrewMobileFinancialApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_crew_can_clock_in_finish_and_correct_only_their_own_assignment(): void
    {
        [, $profile] = $this->authenticatedCrew();
        $assignment = $this->assignment($profile);

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/assignments/'.$assignment->uuid.'/clock-in')->assertOk()
            ->assertJsonPath('data.locked', false);
        $recordedClockIn = $assignment->timeEntry()->firstOrFail()->actual_clock_in_at;
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/assignments/'.$assignment->uuid.'/clock-in')
            ->assertOk()->assertJsonPath('message', 'Clock-in already recorded.');
        $this->assertTrue($recordedClockIn->equalTo($assignment->timeEntry()->firstOrFail()->actual_clock_in_at));
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/assignments/'.$assignment->uuid.'/clock-out')->assertOk();
        $this->assertNotNull($assignment->timeEntry()->firstOrFail()->actual_finish_at);

        $other = $this->assignment($this->completeProfile());
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->putJson('/api/v1/assignments/'.$other->uuid.'/time', ['actual_clock_in_at' => '08:00'])
            ->assertNotFound();
    }

    public function test_timesheets_and_invoices_are_limited_to_the_current_crew_and_bank_values_are_redacted(): void
    {
        [, $profile] = $this->authenticatedCrew();
        $assignment = $this->assignment($profile);
        $date = $assignment->shift->shift_date->toDateString();
        $entry = $assignment->timeEntry()->create(['actual_clock_in_at' => "$date 08:00:00", 'actual_finish_at' => "$date 10:00:00"]);
        $invoice = CrewInvoice::query()->create([
            'crew_profile_id' => $profile->id, 'source' => 'dancepro', 'invoice_number' => '15',
            'invoice_style' => 'modern', 'issuer_snapshot' => ['bank_account_name' => 'Alex', 'bank_name' => 'Test Bank', 'bank_bsb' => '123-456', 'bank_account_number' => '12345678'],
            'period_start' => $date, 'period_end' => $date, 'status' => 'pending_payment',
            'subtotal' => 100, 'allowance_total' => 0, 'total' => 100, 'superable_total' => 100, 'submitted_at' => now(),
        ]);
        $invoice->lines()->create(['assignment_time_entry_id' => $entry->id, 'snapshot' => ['event' => 'Past Event'], 'base_amount' => 100, 'allowance_amount' => 0, 'line_total' => 100]);

        $this->getJson('/api/v1/timesheets')->assertOk()->assertJsonPath('data.0.id', $assignment->uuid);
        $response = $this->getJson('/api/v1/invoices/'.$invoice->uuid)->assertOk()
            ->assertJsonPath('data.payment_details.bsb_last_four', '3456')
            ->assertJsonPath('data.payment_details.account_number_last_four', '5678');
        $this->assertStringNotContainsString('12345678', $response->getContent());

        $otherInvoice = CrewInvoice::query()->create([
            'crew_profile_id' => $this->completeProfile()->id, 'period_start' => $date, 'period_end' => $date,
            'status' => 'draft', 'subtotal' => 1, 'allowance_total' => 0, 'total' => 1, 'superable_total' => 1,
        ]);
        $this->getJson('/api/v1/invoices/'.$otherInvoice->uuid)->assertNotFound();
    }

    public function test_crew_can_list_and_securely_sign_an_active_contract_during_onboarding(): void
    {
        [$user, $profile] = $this->authenticatedCrew(false);
        $contract = CrewContract::factory()->create([
            'status' => CrewContractStatus::Active, 'content' => '<p>Terms</p>',
            'document_checksum' => hash('sha256', '<p>Terms</p>'),
        ]);

        $this->getJson('/api/v1/contracts')->assertOk()->assertJsonPath('data.0.id', $contract->uuid);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/contracts/'.$contract->uuid.'/sign', [
                'signed_name' => 'Alex Example', 'accept_contract' => true, 'password' => 'wrong-password',
            ])->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/contracts/'.$contract->uuid.'/sign', [
                'signed_name' => 'Alex Example', 'accept_contract' => true, 'password' => 'current-password',
            ])->assertOk();

        $signature = $profile->contractSignatures()->firstOrFail();
        $this->assertSame($contract->document_checksum, $signature->contract_checksum);
        $this->assertNotNull($user->refresh()->onboarding_completed_at);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/contracts/'.$contract->uuid.'/sign', [
                'signed_name' => 'Alex Example', 'accept_contract' => true, 'password' => 'current-password',
            ])->assertOk();
        $this->assertDatabaseCount('crew_contract_signatures', 1);
    }

    /** @return array{User, CrewProfile} */
    private function authenticatedCrew(bool $onboarded = true): array
    {
        $user = User::factory()->crew()->create(['password' => 'current-password', 'onboarding_completed_at' => $onboarded ? now() : null]);
        $profile = $this->completeProfile($user);
        Sanctum::actingAs($user, [TokenAbility::CrewMobile->value]);

        return [$user, $profile];
    }

    private function completeProfile(?User $user = null): CrewProfile
    {
        return CrewProfile::factory()->for($user ?? User::factory()->crew()->create())->create([
            'phone' => '0400 000 000', 'address_line_1' => '1 Test Street', 'suburb' => 'Perth',
            'state' => 'WA', 'postcode' => '6000', 'working_with_children_number' => 'WWC123',
            'working_with_children_expiry' => today()->addYear(),
        ]);
    }

    private function assignment(CrewProfile $profile)
    {
        $role = CrewRole::query()->firstOrCreate(['code' => 'competition-videographer'], ['name' => 'Videographer', 'event_type' => 'competition', 'is_active' => true]);
        $event = SchedulingEvent::query()->create(['name' => 'Past Event '.Str::random(4), 'event_type' => 'competition', 'event_date' => today()->subDay()]);
        $shift = $event->shifts()->create(['shift_date' => today()->subDay(), 'period' => 'morning', 'posted_arrival_at' => now()->subDay()->setTime(8, 0)]);

        return $shift->assignments()->create(['crew_profile_id' => $profile->id, 'crew_role_id' => $role->id, 'status' => 'published']);
    }
}
