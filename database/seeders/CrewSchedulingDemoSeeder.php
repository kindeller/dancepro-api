<?php

namespace Database\Seeders;

use App\Features\Crew\Models\CrewContract;
use App\Features\Crew\Models\CrewContractSignature;
use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Models\CrewRole;
use App\Features\Customers\Support\UserType;
use App\Features\Scheduling\Models\CrewAvailabilityResponse;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Scheduling\Models\SchedulingShift;
use App\Features\Venues\Models\Venue;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use RuntimeException;

class CrewSchedulingDemoSeeder extends Seeder
{
    private const PASSWORD = 'local-demo-password';

    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('CrewSchedulingDemoSeeder may only run locally.');
        }

        $staff = User::query()->where('email', 'staff@dancepro.test')->firstOrFail();
        $roles = $this->roles();
        $crew = $this->crew($roles, $staff);
        $this->contract($staff, $crew);
        $this->events($this->venues(), $roles, $crew);
        $this->resetNextShiftWorkflow();

        $this->command?->info('Fictional scheduling data seeded: 7 crew, 4 venues and 4 events.');
        $this->command?->info('The next Perth Dance Festival shift has been reset for clock-in, pre-start and clock-out testing.');
        $this->command?->warn('Crew demo logins use password: '.self::PASSWORD);
    }

    private function roles(): array
    {
        foreach (['competition-videographer' => ['Competition Videographer V', 'competition'], 'concert-videographer' => ['Concert Videographer V', 'concert'], 'photographer-p' => ['Dress Rehearsal Photographer P', 'concert'], 'concert-dr-portrait-assistant' => ['Concert DR Portrait Assistant A', 'concert'], 'concert-photographer-p1' => ['Concert Photographer P1', 'concert'], 'concert-photographer-p2' => ['Concert Photographer P2', 'concert'], 'competition-photographer-p1' => ['Competition Photographer P1', 'competition'], 'competition-photographer-p2' => ['Competition Photographer P2', 'competition'], 'team-leader' => ['Team Leader', null]] as $code => [$name, $eventType]) {
            $roles[$code] = CrewRole::query()->updateOrCreate(['code' => $code], ['name' => $name, 'event_type' => $eventType, 'is_active' => true]);
        }

        return $roles;
    }

    private function crew(array $roles, User $staff): array
    {
        $definitions = [
            'owner' => ['Morgan Vale', $staff->email, '0400 200 001', 'M', 'M', 7, ['competition-videographer', 'concert-videographer', 'team-leader'], ['Toyota', 'RAV4', '1DPA001', 'Silver']],
            'jess' => ['Jess Morgan', 'jess.crew@dancepro.test', '0400 200 002', 'S', 'S', 5, ['photographer-p', 'concert-photographer-p1', 'competition-photographer-p1'], ['Mazda', 'CX-5', '1DPA002', 'Blue']],
            'sam' => ['Sam King', 'sam.crew@dancepro.test', '0400 200 003', 'L', 'L', 4, ['competition-photographer-p2', 'concert-dr-portrait-assistant'], ['Subaru', 'Forester', '1DPA003', 'White']],
            'nina' => ['Nina Brown', 'nina.crew@dancepro.test', '0400 200 004', 'M', 'M', 3, ['competition-videographer', 'concert-videographer'], ['Hyundai', 'i30', '1DPA004', 'Black']],
            'chris' => ['Chris Taylor', 'chris.crew@dancepro.test', '0400 200 005', 'XL', 'XL', 2, ['concert-photographer-p1', 'concert-photographer-p2', 'competition-photographer-p1', 'competition-photographer-p2'], ['Ford', 'Ranger', '1DPA005', 'Grey']],
            'lauren' => ['Lauren Miles', 'lauren.crew@dancepro.test', '0400 200 006', 'S', 'S', 1, ['competition-photographer-p2'], ['Kia', 'Cerato', '1DPA006', 'Red']],
            'dylan' => ['Dylan Park', 'dylan.crew@dancepro.test', '0400 200 007', 'M', 'L', 0, ['competition-videographer', 'concert-videographer'], ['Honda', 'CR-V', '1DPA007', 'White']],
        ];

        foreach ($definitions as $key => [$name, $email, $phone, $shirt, $jacket, $years, $roleCodes, $vehicle]) {
            $user = $key === 'owner'
                ? $staff
                : User::withTrashed()->updateOrCreate(['email' => $email], ['name' => $name, 'type' => UserType::Crew->value, 'password' => self::PASSWORD, 'is_active' => $key !== 'lauren', 'email_verified_at' => now()->subYear(), 'deleted_at' => null]);
            $profile = CrewProfile::withTrashed()->updateOrCreate(['user_id' => $user->id], [
                'legal_name' => $name, 'preferred_name' => strtok($name, ' '), 'phone' => $phone,
                'shirt_size' => $shirt, 'jacket_size' => $jacket,
                'commencement_date' => now()->subYears($years)->subMonths(3)->toDateString(),
                'address_line_1' => (10 + $years).' Example Street', 'suburb' => 'Perth', 'state' => 'WA', 'postcode' => '6000',
                'emergency_contact_name' => 'Fictional Emergency Contact', 'emergency_contact_relationship' => 'Family', 'emergency_contact_phone' => '0400 999 00'.$years,
                'working_with_children_number' => 'WWC-DEMO-'.strtoupper($key), 'working_with_children_expiry' => now()->addYears($key === 'dylan' ? 1 : 3)->toDateString(),
                'owned_equipment' => $key === 'alex' ? 'Sony camera kit, audio recorder and tripods' : 'Personal camera bag and standard accessories',
                'usual_travel_area' => $key === 'nina' ? 'Perth metro and south west' : 'Perth metro',
                'internal_notes' => 'Fictional local-development crew record.', 'deleted_at' => null,
            ]);
            foreach ($roleCodes as $roleCode) {
                $profile->roles()->syncWithoutDetaching([$roles[$roleCode]->id => ['status' => $key === 'dylan' ? 'training' : 'approved', 'effective_from' => now()->subYear()->toDateString(), 'notes' => $key === 'dylan' ? 'Training alongside a senior crew member.' : null]]);
            }
            $profile->vehicles()->updateOrCreate(['registration' => $vehicle[2]], ['make' => $vehicle[0], 'model' => $vehicle[1], 'colour' => $vehicle[3]]);
            if ($key === 'owner') {
                $profile->vehicles()->updateOrCreate(['registration' => '1DPA101'], ['make' => 'Toyota', 'model' => 'HiAce', 'colour' => 'White', 'notes' => 'Useful for equipment transport.']);
            }
            $crew[$key] = $profile;
        }

        return $crew;
    }

    private function contract(User $staff, array $crew): void
    {
        $contract = CrewContract::query()->updateOrCreate(['name' => 'DancePro Crew Agreement', 'version' => '2026.1'], ['status' => 'active', 'effective_from' => now()->startOfYear()->toDateString(), 'content' => 'Fictional placeholder contract for local interface development only.', 'created_by_user_id' => $staff->id]);
        foreach ($crew as $key => $profile) {
            $signed = ! in_array($key, ['lauren', 'dylan'], true);
            CrewContractSignature::query()->updateOrCreate(['crew_contract_id' => $contract->id, 'crew_profile_id' => $profile->id], [
                'status' => $signed ? 'signed' : 'pending', 'signed_at' => $signed ? now()->subMonths(6) : null,
                'recording_method' => $signed ? 'manual_existing' : null, 'recorded_by_user_id' => $signed ? $staff->id : null,
                'recorded_at' => $signed ? now()->subMonths(6) : null, 'recording_note' => $signed ? 'Placeholder existing staff signature.' : null,
            ]);
        }
    }

    private function venues(): array
    {
        $definitions = [
            'crown' => ['Crown Theatre', 'Great Eastern Highway', 'Burswood', '6100', null, 'Free and paid parking onsite.'],
            'regal' => ['Regal Theatre', '474 Hay Street', 'Subiaco', '6008', 'Venue access is from Alvan Street.', null],
            'mandurah' => ['Mandurah Performing Arts Centre', '9 Ormsby Terrace', 'Mandurah', '6210', null, 'Free parking onsite.'],
            'success' => ['Emmanuel Catholic College', '122 Hammond Road', 'Success', '6164', null, null],
        ];
        foreach ($definitions as $key => [$name, $address, $suburb, $postcode, $access, $parking]) {
            $venues[$key] = Venue::query()->updateOrCreate(['name' => $name], ['address_line_1' => $address, 'suburb' => $suburb, 'state' => 'WA', 'postcode' => $postcode, 'access_notes' => $access, 'parking_notes' => $parking]);
        }

        return $venues;
    }

    private function events(array $venues, array $roles, array $crew): void
    {
        $definitions = [
            ['Perth Dance Festival', $venues['crown'], 'open', now()->addDays(35), 3],
            ['Express Dance Challenge', $venues['success'], 'draft', now()->addDays(70), 2],
            ['Coastal Dance Championships', $venues['mandurah'], 'closed', now()->addDays(95), 2],
            ['Graceful Moves Showcase', $venues['regal'], 'open', now()->addDays(120), 1],
        ];
        foreach ($definitions as $eventIndex => [$name, $venue, $status, $startDate, $dayCount]) {
            $event = SchedulingEvent::query()->updateOrCreate(['name' => $name], ['venue_id' => $venue->id, 'event_type' => $eventIndex === 3 ? 'concert' : 'competition', 'event_date' => $startDate->toDateString(), 'availability_status' => $status, 'availability_deadline' => $status === 'open' ? now()->addDays(14 + $eventIndex)->setTime(17, 0) : null, 'roster_status' => $eventIndex === 0 ? 'published' : 'draft', 'roster_published_at' => $eventIndex === 0 ? now()->subDay() : null, 'admin_notes' => 'Fictional scheduling event for local interface development.']);
            $eventRoleCodes = $eventIndex === 3
                ? ['concert-videographer', 'concert-photographer-p1']
                : ['competition-videographer', 'competition-photographer-p1', 'competition-photographer-p2'];
            foreach ($eventRoleCodes as $roleCode) {
                $event->roleRequirements()->updateOrCreate(['crew_role_id' => $roles[$roleCode]->id], ['quantity' => 1]);
            }
            $keptShiftIds = [];
            for ($day = 0; $day < $dayCount; $day++) {
                $periods = $eventIndex === 3 ? [null] : ['morning', 'afternoon'];
                foreach ($periods as $periodIndex => $period) {
                    $date = $startDate->copy()->addDays($day);
                    $start = $period === null ? '18:00' : ($period === 'morning' ? '08:00' : '13:00');
                    $finish = $period === null ? '21:00' : ($period === 'morning' ? '12:00' : '18:00');
                    $setup = $period === null || ($day === 0 && $period === 'morning');
                    $setDown = $period === null || ($day === $dayCount - 1 && $period === 'afternoon');
                    $shift = SchedulingShift::query()->updateOrCreate(['scheduling_event_id' => $event->id, 'shift_date' => $date->toDateString(), 'period' => $period], ['requires_setup' => $setup, 'requires_set_down' => $setDown, 'posted_arrival_at' => Carbon::parse($date->toDateString().' '.$start)->subMinutes($setup ? 90 : ($period === 'morning' ? 45 : 30)), 'starts_at' => Carbon::parse($date->toDateString().' '.$start), 'estimated_finish_at' => Carbon::parse($date->toDateString().' '.$finish)->addMinutes($setDown ? 20 : 0)]);
                    $keptShiftIds[] = $shift->id;
                    if ($status === 'open') {
                        $this->responses($shift, $crew, $eventIndex + $day + $periodIndex);
                    }
                    $assignmentCrew = $eventIndex === 3
                        ? ['concert-videographer' => 'owner', 'concert-photographer-p1' => 'jess']
                        : ['competition-videographer' => 'owner', 'competition-photographer-p1' => 'jess', 'competition-photographer-p2' => 'sam'];
                    foreach ($assignmentCrew as $assignmentIndex => $crewKey) {
                        $role = $roles[$assignmentIndex];
                        $published = $eventIndex === 0;
                        $acknowledged = $published && ($day + $periodIndex + $role->id) % 2 === 0;
                        $isVideographer = str_ends_with($assignmentIndex, 'videographer');
                        $assignment = $shift->assignments()->updateOrCreate(['crew_role_id' => $role->id], ['crew_profile_id' => $crew[$crewKey]->id, 'is_team_leader' => $isVideographer, 'status' => $published ? 'published' : 'draft', 'published_at' => $published ? now()->subDay() : null, 'notified_at' => $published ? now()->subDay() : null, 'acknowledgement_status' => $acknowledged ? 'acknowledged' : 'not_acknowledged', 'acknowledged_at' => $acknowledged ? now()->subHours(8) : null]);
                        if ($eventIndex === 3 && $assignmentIndex === 'concert-videographer') {
                            $assignment->equipmentResponsibilities()->updateOrCreate(['item_code' => 'video_1'], ['is_bringing' => false, 'is_taking' => true, 'other_notes' => 'David is bringing the kit to the venue.']);
                        }
                        if ($eventIndex === 3 && $assignmentIndex === 'concert-photographer-p1') {
                            $assignment->equipmentResponsibilities()->updateOrCreate(['item_code' => 'backdrop_1'], ['is_bringing' => true, 'is_taking' => false]);
                        }
                        if ($isVideographer) {
                            $assignment->equipmentResponsibilities()->updateOrCreate(['item_code' => 'media'], ['is_bringing' => false, 'is_taking' => true]);
                        }
                        if ($published) {
                            $shift->availabilityResponses()->where('crew_profile_id', $crew[$crewKey]->id)->update(['locked_at' => now()->subDay()]);
                        }
                    }
                }
            }
            $event->shifts()->whereNotIn('id', $keptShiftIds)->delete();
        }
    }

    private function responses(SchedulingShift $shift, array $crew, int $offset): void
    {
        foreach (array_values($crew) as $index => $profile) {
            if (($index + $offset) % 5 === 0) {
                continue;
            }
            CrewAvailabilityResponse::query()->updateOrCreate(['scheduling_shift_id' => $shift->id, 'crew_profile_id' => $profile->id], ['status' => ($index + $offset) % 4 === 0 ? 'unavailable' : 'available', 'note' => ($index + $offset) % 4 === 0 ? 'Fictional family commitment.' : null, 'responded_at' => now()->subDays(($index % 3) + 1)]);
        }
    }

    private function resetNextShiftWorkflow(): void
    {
        $event = SchedulingEvent::query()
            ->where('name', 'Perth Dance Festival')
            ->firstOrFail();

        $shift = $event->shifts()
            ->orderBy('shift_date')
            ->orderBy('posted_arrival_at')
            ->firstOrFail();

        $shift->assignments()
            ->where('status', 'published')
            ->with(['checklistCompletions', 'timeEntry.invoiceLine'])
            ->get()
            ->each(function ($assignment): void {
                $assignment->checklistCompletions()->delete();

                if ($assignment->timeEntry?->invoiceLine === null) {
                    $assignment->timeEntry?->delete();
                }
            });
    }
}
