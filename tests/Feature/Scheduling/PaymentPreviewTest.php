<?php

namespace Tests\Feature\Scheduling;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Models\CrewRole;
use App\Features\Scheduling\Actions\SavePayRateVersion;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Scheduling\Models\SchedulingShiftAssignment;
use App\Features\Scheduling\Services\PaymentPreviewCalculator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_rates_are_effective_dated_and_historical_calculations_are_preserved(): void
    {
        $save = app(SavePayRateVersion::class);
        $save->execute(['rate_key' => 'competition_hourly', 'amount' => 40, 'effective_from' => '2026-01-01', 'is_superable' => true]);
        $save->execute(['rate_key' => 'competition_hourly', 'amount' => 45, 'effective_from' => '2026-07-01', 'is_superable' => true]);

        $june = $this->assignment('competition', 'competition-videographer', '2026-06-10');
        $august = $this->assignment('competition', 'competition-videographer', '2026-08-10');
        $this->addTime($june, '2026-06-10 08:00:00', '2026-06-10 10:30:00');
        $this->addTime($august, '2026-08-10 08:00:00', '2026-08-10 10:00:00');

        $this->assertSame(100.0, app(PaymentPreviewCalculator::class)->execute($june)['base']);
        $this->assertSame(90.0, app(PaymentPreviewCalculator::class)->execute($august)['base']);
        $this->assertDatabaseHas('pay_rates', ['rate_key' => 'competition_hourly', 'amount' => 40, 'effective_until' => '2026-06-30 00:00:00']);
    }

    public function test_team_leader_rate_and_allowances_are_included_in_preview(): void
    {
        $this->rate('competition_team_leader_hourly', 50);
        $this->rate('equipment_collection_allowance', 15, false);
        $assignment = $this->assignment('competition', 'competition-photographer-p1', '2026-09-10', true);
        $this->addTime($assignment, '2026-09-10 08:00:00', '2026-09-10 12:00:00');
        $assignment->allowances()->create(['allowance_key' => 'equipment_collection_allowance']);

        $preview = app(PaymentPreviewCalculator::class)->execute($assignment->fresh());

        $this->assertSame('competition_team_leader_hourly', $preview['rateKey']);
        $this->assertSame(200.0, $preview['base']);
        $this->assertSame(215.0, $preview['total']);
        $this->assertSame(200.0, $preview['superable']);
    }

    public function test_hourly_invoice_times_round_to_quarter_hours_without_starting_before_posted_arrival(): void
    {
        $this->rate('competition_hourly', 40);
        $assignment = $this->assignment('competition', 'competition-videographer', '2026-09-10');
        $this->addTime($assignment, '2026-09-10 08:07:00', '2026-09-10 09:05:00');

        $preview = app(PaymentPreviewCalculator::class)->execute($assignment);

        $this->assertSame('08:00', $preview['payableStart']->format('H:i'));
        $this->assertSame('09:15', $preview['payableFinish']->format('H:i'));
        $this->assertSame(1.25, $preview['hours']);
        $this->assertSame(50.0, $preview['base']);

        $assignment->timeEntry()->update([
            'actual_clock_in_at' => '2026-09-10 07:52:00',
            'actual_finish_at' => '2026-09-10 09:00:00',
        ]);
        $preview = app(PaymentPreviewCalculator::class)->execute($assignment->fresh());

        $this->assertSame('08:00', $preview['payableStart']->format('H:i'));
        $this->assertSame('09:00', $preview['payableFinish']->format('H:i'));
    }

    public function test_concert_fixed_rate_over_seven_hours_is_flagged_for_manual_calculation(): void
    {
        $this->rate('concert_fixed', 300);
        $assignment = $this->assignment('concert', 'concert-photographer-p1', '2026-09-10');
        $this->addTime($assignment, '2026-09-10 12:00:00', '2026-09-10 20:00:00');

        $preview = app(PaymentPreviewCalculator::class)->execute($assignment);

        $this->assertSame(300.0, $preview['total']);
        $this->assertTrue($preview['flags']->contains('Over 7 hours — manual calculation required.'));
    }

    public function test_concert_p2_trainee_uses_hourly_rate(): void
    {
        $this->rate('concert_hourly', 30);
        $assignment = $this->assignment('concert', 'concert-photographer-p2', '2026-09-10');
        $assignment->crewProfile->roles()->attach($assignment->crew_role_id, ['status' => 'training']);
        $this->addTime($assignment, '2026-09-10 17:00:00', '2026-09-10 20:00:00');

        $preview = app(PaymentPreviewCalculator::class)->execute($assignment);

        $this->assertSame('concert_hourly', $preview['rateKey']);
        $this->assertSame(90.0, $preview['total']);
    }

    public function test_admin_can_manage_rates_and_assignment_allowances(): void
    {
        $admin = User::factory()->staff()->create();
        $assignment = $this->assignment('competition', 'competition-videographer', '2026-09-10');

        $this->actingAs($admin)->get(route('admin.payments.index'))->assertOk()->assertSee('Current local amounts are temporary placeholders');
        $this->actingAs($admin)->post(route('admin.payments.rates.store'), [
            'rate_key' => 'competition_hourly', 'amount' => '42.50', 'effective_from' => '2026-01-01', 'is_superable' => '1',
        ])->assertRedirect();
        $this->actingAs($admin)->put(route('admin.scheduling-assignments.allowances.update', $assignment), [
            'allowances' => ['equipment_collection_allowance', 'equipment_return_allowance'],
        ])->assertRedirect();

        $this->assertDatabaseHas('pay_rates', ['rate_key' => 'competition_hourly', 'amount' => 42.50]);
        $this->assertCount(2, $assignment->allowances()->get());
    }

    public function test_crew_specific_rate_overrides_the_general_rate_and_matrix_can_be_saved(): void
    {
        $admin = User::factory()->staff()->create();
        $assignment = $this->assignment('competition', 'competition-videographer', '2026-09-10');
        $this->rate('competition_hourly', 40);
        app(SavePayRateVersion::class)->execute([
            'crew_profile_id' => $assignment->crew_profile_id,
            'rate_key' => 'competition_hourly',
            'amount' => 55,
            'effective_from' => '2026-01-01',
            'is_superable' => true,
        ]);
        $this->addTime($assignment, '2026-09-10 08:00:00', '2026-09-10 10:00:00');

        $this->assertSame(110.0, app(PaymentPreviewCalculator::class)->execute($assignment)['total']);
        $this->actingAs($admin)->get(route('admin.payments.index'))->assertOk()
            ->assertSee('Crew-specific rates in one compact table.')
            ->assertSee($assignment->crewProfile->preferred_name);
        $this->actingAs($admin)->post(route('admin.payments.matrix.store'), [
            'effective_from' => '2026-10-01',
            'rates' => [$assignment->crew_profile_id => ['competition_hourly' => 60]],
        ])->assertRedirect();
        $this->assertDatabaseHas('pay_rates', ['crew_profile_id' => $assignment->crew_profile_id, 'rate_key' => 'competition_hourly', 'amount' => 60]);
    }

    private function rate(string $key, float $amount, bool $superable = true): void
    {
        app(SavePayRateVersion::class)->execute(['rate_key' => $key, 'amount' => $amount, 'effective_from' => '2026-01-01', 'is_superable' => $superable]);
    }

    private function assignment(string $eventType, string $roleCode, string $date, bool $teamLeader = false): SchedulingShiftAssignment
    {
        $user = User::factory()->crew()->create();
        $profile = CrewProfile::factory()->for($user)->create();
        $role = CrewRole::query()->firstOrCreate(['code' => $roleCode], ['name' => str($roleCode)->replace('-', ' ')->title(), 'event_type' => $eventType, 'is_active' => true]);
        $event = SchedulingEvent::query()->create(['name' => 'Event', 'event_type' => $eventType, 'event_date' => $date]);
        $shift = $event->shifts()->create(['shift_date' => $date, 'period' => $eventType === 'competition' ? 'morning' : null, 'posted_arrival_at' => "$date 08:00:00", 'starts_at' => "$date 08:30:00", 'estimated_finish_at' => "$date 20:00:00"]);

        return $shift->assignments()->create(['crew_profile_id' => $profile->id, 'crew_role_id' => $role->id, 'status' => 'published', 'is_team_leader' => $teamLeader]);
    }

    private function addTime(SchedulingShiftAssignment $assignment, string $start, string $finish): void
    {
        $assignment->timeEntry()->create(['actual_clock_in_at' => $start, 'payable_start_at' => $start, 'actual_finish_at' => $finish]);
    }
}
