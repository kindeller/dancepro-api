<?php

namespace Tests\Feature\Timesheets;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Models\CrewRole;
use App\Features\Scheduling\Actions\SavePayRateVersion;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Timesheets\Models\CrewInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimesheetInvoiceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_crew_previews_and_accepts_a_competition_invoice_which_becomes_pending_payment(): void
    {
        $admin = User::factory()->staff()->create();
        $crewUser = User::factory()->crew()->create();
        $profile = CrewProfile::factory()->for($crewUser)->create([
            'legal_name' => 'Alex Contractor', 'preferred_name' => 'Alex', 'phone' => '0400 000 000', 'address_line_1' => '1 Test Street',
            'suburb' => 'Perth', 'state' => 'WA', 'postcode' => '6000', 'abn' => '11 222 333 444',
            'bank_account_name' => 'Alex Contractor', 'bank_name' => 'Test Bank', 'bank_bsb' => '123-456', 'bank_account_number' => '12345678',
        ]);
        $role = CrewRole::query()->firstOrCreate(['code' => 'competition-videographer'], ['name' => 'Videographer', 'event_type' => 'competition', 'is_active' => true]);
        $date = now()->subWeek()->toDateString();
        $event = SchedulingEvent::query()->create(['name' => 'Dance Challenge', 'event_type' => 'competition', 'event_date' => $date]);
        $shift = $event->shifts()->create(['shift_date' => $date, 'period' => 'morning']);
        $assignment = $shift->assignments()->create(['crew_profile_id' => $profile->id, 'crew_role_id' => $role->id, 'status' => 'published']);
        $entry = $assignment->timeEntry()->create(['actual_clock_in_at' => "$date 08:00:00", 'payable_start_at' => "$date 08:00:00", 'actual_finish_at' => "$date 12:00:00"]);
        app(SavePayRateVersion::class)->execute(['rate_key' => 'competition_hourly', 'amount' => 50, 'effective_from' => now()->subYear()->toDateString(), 'is_superable' => true]);

        $this->actingAs($crewUser)->get(route('crew.timesheets.index'))->assertOk()->assertSee('Pending')->assertSee('Dance Challenge');
        $this->actingAs($admin)->get(route('admin.timesheets.index'))->assertOk()->assertSee('Dance Challenge')->assertSee('$200.00')->assertSee('badge attention', false);

        $otherEvent = SchedulingEvent::query()->create(['name' => 'Different Competition', 'event_type' => 'competition', 'event_date' => $date]);
        $otherShift = $otherEvent->shifts()->create(['shift_date' => $date, 'period' => 'afternoon']);
        $otherAssignment = $otherShift->assignments()->create(['crew_profile_id' => $profile->id, 'crew_role_id' => $role->id, 'status' => 'published']);
        $otherEntry = $otherAssignment->timeEntry()->create(['actual_clock_in_at' => "$date 13:00:00", 'payable_start_at' => "$date 13:00:00", 'actual_finish_at' => "$date 15:00:00", 'approval_status' => 'approved', 'locked_at' => now()]);

        $payload = ['entry_ids' => [$entry->id], 'starting_invoice_number' => 37, 'invoice_style' => 'modern'];
        $this->actingAs($crewUser)->post(route('crew.timesheets.invoices.preview'), $payload)
            ->assertOk()->assertSee('Invoice 37 preview')->assertSee('Dance Challenge')->assertSee('$200.00')->assertSee('Invoice total');
        $this->actingAs($crewUser)->post(route('crew.timesheets.invoices.accept'), $payload)->assertRedirect();
        $invoice = CrewInvoice::query()->firstOrFail();
        $this->assertSame('200.00', $invoice->total);
        $this->assertSame('pending_payment', $invoice->status);
        $this->assertFalse($otherEntry->invoiceLine()->exists(), 'A separate competition must not be combined into this invoice.');
        $this->actingAs($crewUser)->get(route('crew.timesheets.index'))->assertOk()->assertSee('status-pill attention', false)->assertSee('Pending payment');
        $this->actingAs($crewUser)->get(route('crew.timesheets.invoices.show', $invoice))->assertOk()->assertSee('Invoice 37')->assertSee('Pending payment')->assertDontSee('Pending_payment');
        $this->actingAs($crewUser)->get(route('crew.timesheets.invoices.print', $invoice))->assertOk()->assertSee('A4 portrait preview')->assertSee('Alex Contractor')->assertSee('ABN 77 221 718 867')->assertDontSee('dancepro-logo');
        $this->actingAs($admin)->get(route('admin.timesheets.index'))->assertOk()->assertSee('badge success', false);
        $this->actingAs($admin)->get(route('admin.timesheets.invoices.index'))->assertOk()->assertSee('Pending payment')->assertSee('Alex')->assertSee('badge attention', false);
        $this->actingAs($admin)
            ->get(route('admin.timesheets.invoices.export', $invoice))
            ->assertOk()
            ->assertDownload($invoice->invoice_number.'.csv');
        $this->assertSame('pending_payment', $invoice->refresh()->status);
        $this->actingAs($admin)->patch(route('admin.timesheets.invoices.update', $invoice), ['action' => 'paid'])->assertRedirect();
        $this->assertSame('paid', $invoice->refresh()->status);
        $this->actingAs($crewUser)->get(route('crew.timesheets.index'))->assertOk()->assertSee('status-pill done', false)->assertSee('Paid');
        $this->actingAs($admin)->get(route('admin.timesheets.invoices.index'))->assertOk()->assertSee('badge success', false);
        $this->assertNotNull($invoice->invoice_number);
        $this->assertSame('37', $invoice->invoice_number);
        $this->assertSame(38, $profile->refresh()->next_invoice_number);
    }

    public function test_crew_can_mark_emailed_concert_invoice_work_complete_without_storing_it(): void
    {
        $crewUser = User::factory()->crew()->create();
        $profile = CrewProfile::factory()->for($crewUser)->create();
        $role = CrewRole::query()->firstOrCreate(['code' => 'concert-videographer'], ['name' => 'Concert Videographer', 'event_type' => 'concert', 'is_active' => true]);
        app(SavePayRateVersion::class)->execute(['rate_key' => 'concert_fixed', 'amount' => 180, 'effective_from' => now()->subYear()->toDateString(), 'is_superable' => true]);
        $entries = collect();
        foreach ([now()->subDays(3), now()->subDay()] as $date) {
            $event = SchedulingEvent::query()->create(['name' => 'School Concert '.$date->day, 'event_type' => 'concert', 'event_date' => $date->toDateString()]);
            $shift = $event->shifts()->create(['shift_date' => $date->toDateString()]);
            $assignment = $shift->assignments()->create(['crew_profile_id' => $profile->id, 'crew_role_id' => $role->id, 'status' => 'published']);
            $entries->push($assignment->timeEntry()->create(['actual_clock_in_at' => $date->copy()->setTime(17, 0), 'payable_start_at' => $date->copy()->setTime(17, 0), 'actual_finish_at' => $date->copy()->setTime(20, 0)]));
        }

        $this->actingAs($crewUser)->post(route('crew.timesheets.external-work-complete'), [
            'entry_ids' => $entries->pluck('id')->all(),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseCount('crew_invoices', 0);
        $this->assertTrue($entries->every(fn ($entry) => $entry->refresh()->approval_status === 'externally_invoiced'));
    }

    public function test_competitions_cannot_be_combined_on_one_invoice(): void
    {
        $crewUser = User::factory()->crew()->create();
        $profile = CrewProfile::factory()->for($crewUser)->create();
        $role = CrewRole::query()->firstOrCreate(['code' => 'competition-photographer'], ['name' => 'Photographer', 'event_type' => 'competition', 'is_active' => true]);
        app(SavePayRateVersion::class)->execute(['rate_key' => 'competition_hourly', 'amount' => 50, 'effective_from' => now()->subYear()->toDateString(), 'is_superable' => true]);
        $entries = collect();

        foreach (['Competition One', 'Competition Two'] as $offset => $name) {
            $date = now()->subDays($offset + 1);
            $event = SchedulingEvent::query()->create(['name' => $name, 'event_type' => 'competition', 'event_date' => $date->toDateString()]);
            $shift = $event->shifts()->create(['shift_date' => $date->toDateString()]);
            $assignment = $shift->assignments()->create(['crew_profile_id' => $profile->id, 'crew_role_id' => $role->id, 'status' => 'published']);
            $entries->push($assignment->timeEntry()->create(['actual_clock_in_at' => $date->copy()->setTime(8, 0), 'payable_start_at' => $date->copy()->setTime(8, 0), 'actual_finish_at' => $date->copy()->setTime(10, 0)]));
        }

        $this->actingAs($crewUser)->post(route('crew.timesheets.invoices.preview'), [
            'entry_ids' => $entries->pluck('id')->all(), 'starting_invoice_number' => 1, 'invoice_style' => 'classic',
        ])->assertSessionHasErrors('entry_ids');

        $this->assertDatabaseCount('crew_invoices', 0);
    }

    public function test_past_shift_without_clocking_appears_for_manual_time_confirmation(): void
    {
        $crewUser = User::factory()->crew()->create();
        $profile = CrewProfile::factory()->for($crewUser)->create();
        $role = CrewRole::query()->firstOrCreate(['code' => 'concert-videographer'], ['name' => 'Concert Videographer', 'event_type' => 'concert', 'is_active' => true]);
        app(SavePayRateVersion::class)->execute(['rate_key' => 'concert_fixed', 'amount' => 250, 'effective_from' => now()->subYear()->toDateString(), 'is_superable' => true]);
        $date = now()->subDay()->toDateString();
        $event = SchedulingEvent::query()->create(['name' => 'Concert Without Clocking', 'event_type' => 'concert', 'event_date' => $date]);
        $shift = $event->shifts()->create(['shift_date' => $date, 'starts_at' => "$date 18:00:00", 'estimated_finish_at' => "$date 21:00:00"]);
        $assignment = $shift->assignments()->create(['crew_profile_id' => $profile->id, 'crew_role_id' => $role->id, 'status' => 'published']);

        $this->actingAs($crewUser)->get(route('crew.timesheets.index'))
            ->assertOk()
            ->assertSee('Concert Without Clocking')
            ->assertSee('Confirm times')
            ->assertSee('Enter start and finish times')
            ->assertSee('type="time"', false)
            ->assertDontSee('type="datetime-local"', false);

        $this->actingAs($crewUser)->put(route('crew.assignments.time.update', $assignment), [
            'actual_clock_in_at' => '17:50', 'actual_finish_at' => '21:10',
        ])->assertRedirect();

        $this->assertSame($date, $assignment->fresh()->timeEntry->actual_clock_in_at->toDateString());

        $this->actingAs($crewUser)->get(route('crew.timesheets.index'))
            ->assertOk()->assertSee('Ready to invoice')->assertSee('$250.00');

        $this->actingAs($crewUser)->put(route('crew.assignments.time.update', $assignment), [
            'actual_clock_in_at' => '18:05', 'actual_finish_at' => '21:10',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('18:05', $assignment->fresh()->timeEntry->actual_clock_in_at->format('H:i'));
    }

    public function test_crew_cannot_access_another_crews_invoice(): void
    {
        $owner = CrewProfile::factory()->create();
        $other = User::factory()->crew()->create();
        CrewProfile::factory()->for($other)->create();
        $invoice = CrewInvoice::query()->create(['crew_profile_id' => $owner->id, 'period_start' => today(), 'period_end' => today(), 'status' => 'draft', 'subtotal' => 10, 'allowance_total' => 0, 'total' => 10, 'superable_total' => 10]);

        $this->actingAs($other)->get(route('crew.timesheets.invoices.show', $invoice))->assertForbidden();
    }
}
