<?php

namespace Database\Seeders;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Models\CrewRole;
use App\Features\Scheduling\Models\AssignmentTimeEntry;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Scheduling\Models\SchedulingShiftAssignment;
use App\Features\Timesheets\Actions\CreateSelectedCrewInvoice;
use App\Features\Timesheets\Models\CrewInvoice;
use App\Features\Venues\Models\Venue;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use RuntimeException;

class TimesheetInvoiceDemoSeeder extends Seeder
{
    public function run(CreateSelectedCrewInvoice $createInvoice): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('TimesheetInvoiceDemoSeeder may only run locally.');
        }

        $crew = CrewProfile::query()
            ->where('user_id', User::query()->where('email', 'staff@dancepro.test')->value('id'))
            ->firstOrFail();

        $crew->update([
            'legal_name' => 'Morgan Vale',
            'preferred_name' => 'Morgan',
            'phone' => '0400 200 001',
            'address_line_1' => '17 Example Street',
            'suburb' => 'Perth',
            'state' => 'WA',
            'postcode' => '6000',
            'abn' => '12 345 678 901',
            'bank_account_name' => 'Morgan Vale',
            'bank_name' => 'Demo Bank',
            'bank_bsb' => '123-456',
            'bank_account_number' => '12345678',
        ]);

        $venue = Venue::query()->where('name', 'Regal Theatre')->firstOrFail();
        $competitionRole = CrewRole::query()->where('code', 'competition-videographer')->firstOrFail();
        $concertRole = CrewRole::query()->where('code', 'concert-videographer')->firstOrFail();

        $competitionEntry = $this->completedShift(
            crew: $crew,
            role: $competitionRole,
            venue: $venue,
            eventName: 'DEMO - West Coast Dance Competition',
            eventType: 'competition',
            date: now()->subDays(12)->startOfDay(),
            start: '08:00',
            finish: '16:30',
            period: 'morning',
        );

        foreach ([
            ['DEMO - Riverside Dance Concert', now()->subDays(6)->startOfDay(), '17:00', '21:15'],
            ['DEMO - City Lights Dance Concert', now()->subDays(3)->startOfDay(), '16:30', '20:45'],
        ] as [$name, $date, $start, $finish]) {
            $this->completedShift($crew, $concertRole, $venue, $name, 'concert', $date, $start, $finish);
        }

        $freshCompetitionDate = now()->subDays(2)->startOfDay();
        $this->completedShift($crew, $competitionRole, $venue, 'DEMO - Spring Dance Challenge', 'competition', $freshCompetitionDate, '07:30', '12:15', 'morning');
        $this->completedShift($crew, $competitionRole, $venue, 'DEMO - Spring Dance Challenge', 'competition', $freshCompetitionDate, '12:45', '18:00', 'afternoon');
        $this->completedShift($crew, $concertRole, $venue, 'DEMO - Saturday Showcase', 'concert', now()->subDay()->startOfDay(), '17:15', '21:30');

        $this->shiftNeedingTimeConfirmation(
            $crew,
            $concertRole,
            $venue,
            'DEMO - Moonlight Dance Concert',
            'concert',
            now()->subDays(4)->startOfDay(),
            '17:30',
            '21:30',
        );
        $this->shiftNeedingTimeConfirmation(
            $crew,
            $concertRole,
            $venue,
            'DEMO - Encore Dance Concert',
            'concert',
            now()->subDays(5)->startOfDay(),
            '16:45',
            '20:30',
            actualStart: '16:50',
        );
        $this->shiftNeedingTimeConfirmation(
            $crew,
            $competitionRole,
            $venue,
            'DEMO - Coastal Dance Competition',
            'competition',
            now()->subDays(7)->startOfDay(),
            '08:00',
            '17:00',
            period: 'morning',
        );

        $competitionEvent = $competitionEntry->assignment->shift->schedulingEvent;
        if (! CrewInvoice::query()->where('crew_profile_id', $crew->id)->where('scheduling_event_id', $competitionEvent->id)->exists()) {
            $createInvoice->execute($crew, [$competitionEntry->id], 'classic', $crew->next_invoice_number ?? 101);
        }

        $this->command?->info('Demo payment workflow seeded for staff@dancepro.test, including confirmed timesheets and three shifts requiring time confirmation.');
    }

    private function completedShift(
        CrewProfile $crew,
        CrewRole $role,
        Venue $venue,
        string $eventName,
        string $eventType,
        Carbon $date,
        string $start,
        string $finish,
        ?string $period = null,
    ): AssignmentTimeEntry {
        $event = SchedulingEvent::query()->updateOrCreate(
            ['name' => $eventName],
            [
                'venue_id' => $venue->id,
                'event_type' => $eventType,
                'event_date' => $date->toDateString(),
                'availability_status' => 'closed',
                'roster_status' => 'published',
                'roster_published_at' => $date->copy()->subMonth(),
                'admin_notes' => 'Local demonstration record for testing crew timesheets and invoices.',
            ],
        );
        $shift = $event->shifts()->updateOrCreate(
            ['shift_date' => $date->toDateString(), 'period' => $period],
            [
                'posted_arrival_at' => Carbon::parse($date->toDateString().' '.$start)->subMinutes(30),
                'starts_at' => Carbon::parse($date->toDateString().' '.$start),
                'estimated_finish_at' => Carbon::parse($date->toDateString().' '.$finish),
            ],
        );
        $assignment = SchedulingShiftAssignment::query()->updateOrCreate(
            ['scheduling_shift_id' => $shift->id, 'crew_role_id' => $role->id],
            [
                'crew_profile_id' => $crew->id,
                'status' => 'published',
                'published_at' => $date->copy()->subMonth(),
                'acknowledgement_status' => 'acknowledged',
                'acknowledged_at' => $date->copy()->subWeeks(3),
            ],
        );

        return $assignment->timeEntry()->updateOrCreate(
            ['scheduling_shift_assignment_id' => $assignment->id],
            [
                'actual_clock_in_at' => Carbon::parse($date->toDateString().' '.$start),
                'payable_start_at' => Carbon::parse($date->toDateString().' '.$start),
                'actual_finish_at' => Carbon::parse($date->toDateString().' '.$finish),
            ],
        );
    }

    private function shiftNeedingTimeConfirmation(
        CrewProfile $crew,
        CrewRole $role,
        Venue $venue,
        string $eventName,
        string $eventType,
        Carbon $date,
        string $scheduledStart,
        string $scheduledFinish,
        ?string $period = null,
        ?string $actualStart = null,
    ): void {
        $event = SchedulingEvent::query()->updateOrCreate(
            ['name' => $eventName],
            [
                'venue_id' => $venue->id,
                'event_type' => $eventType,
                'event_date' => $date->toDateString(),
                'availability_status' => 'closed',
                'roster_status' => 'published',
                'roster_published_at' => $date->copy()->subMonth(),
                'admin_notes' => 'Local demonstration record requiring crew time confirmation.',
            ],
        );
        $shift = $event->shifts()->updateOrCreate(
            ['shift_date' => $date->toDateString(), 'period' => $period],
            [
                'posted_arrival_at' => Carbon::parse($date->toDateString().' '.$scheduledStart)->subMinutes(30),
                'starts_at' => Carbon::parse($date->toDateString().' '.$scheduledStart),
                'estimated_finish_at' => Carbon::parse($date->toDateString().' '.$scheduledFinish),
            ],
        );
        $assignment = SchedulingShiftAssignment::query()->updateOrCreate(
            ['scheduling_shift_id' => $shift->id, 'crew_role_id' => $role->id],
            [
                'crew_profile_id' => $crew->id,
                'status' => 'published',
                'published_at' => $date->copy()->subMonth(),
                'acknowledgement_status' => 'acknowledged',
                'acknowledged_at' => $date->copy()->subWeeks(3),
            ],
        );

        if ($actualStart === null) {
            $assignment->timeEntry()->delete();

            return;
        }

        $assignment->timeEntry()->updateOrCreate(
            ['scheduling_shift_assignment_id' => $assignment->id],
            [
                'actual_clock_in_at' => Carbon::parse($date->toDateString().' '.$actualStart),
                'payable_start_at' => Carbon::parse($date->toDateString().' '.$actualStart),
                'actual_finish_at' => null,
            ],
        );
    }
}
