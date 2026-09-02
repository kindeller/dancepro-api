<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $oldP1 = DB::table('crew_roles')->where('code', 'photographer-p1')->first();
        $oldP2 = DB::table('crew_roles')->where('code', 'photographer-p2')->first();

        $competitionP1 = $this->role('competition-photographer-p1', 'Competition Photographer P1', 'competition');
        $competitionP2 = $this->role('competition-photographer-p2', 'Competition Photographer P2', 'competition');
        $concertP1 = $this->role('concert-photographer-p1', 'Concert Photographer P1', 'concert');
        $this->role('photographer-p', 'Dress Rehearsal Photographer P', 'concert');

        if ($oldP1) {
            $this->copyQualifications($oldP1->id, [$competitionP1, $concertP1]);
            $this->moveReferencesForType($oldP1->id, $competitionP1, 'competition');
            $this->moveReferencesForType($oldP1->id, $concertP1, 'concert');
            DB::table('crew_roles')->where('id', $oldP1->id)->update(['is_active' => false]);
        }

        if ($oldP2) {
            $this->copyQualifications($oldP2->id, [$competitionP2]);
            $this->moveReferencesForType($oldP2->id, $competitionP2, 'competition');
            DB::table('crew_roles')->where('id', $oldP2->id)->update(['is_active' => false]);
        }

        DB::table('operational_resources')->where('role_code', 'photographer-stage')->update(['role_code' => 'competition-photographer']);
        DB::table('checklist_templates')->where('role_code', 'photographer-stage')->update(['role_code' => 'competition-photographer']);
    }

    public function down(): void
    {
        // Event-specific role changes may occur after migration, so merging them is unsafe.
    }

    private function role(string $code, string $name, string $eventType): int
    {
        $existing = DB::table('crew_roles')->where('code', $code)->first();
        if ($existing) {
            DB::table('crew_roles')->where('id', $existing->id)->update(['name' => $name, 'event_type' => $eventType, 'is_active' => true, 'updated_at' => now()]);

            return (int) $existing->id;
        }

        return (int) DB::table('crew_roles')->insertGetId([
            'uuid' => (string) Str::uuid(), 'code' => $code, 'name' => $name, 'event_type' => $eventType,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function copyQualifications(int $oldRoleId, array $newRoleIds): void
    {
        foreach (DB::table('crew_role_qualifications')->where('crew_role_id', $oldRoleId)->get() as $qualification) {
            foreach ($newRoleIds as $newRoleId) {
                DB::table('crew_role_qualifications')->updateOrInsert(
                    ['crew_profile_id' => $qualification->crew_profile_id, 'crew_role_id' => $newRoleId],
                    ['status' => $qualification->status, 'effective_from' => $qualification->effective_from, 'effective_until' => $qualification->effective_until, 'notes' => $qualification->notes, 'created_at' => now(), 'updated_at' => now()],
                );
            }
        }
    }

    private function moveReferencesForType(int $oldRoleId, int $newRoleId, string $eventType): void
    {
        $eventIds = DB::table('scheduling_events')->where('event_type', $eventType)->select('id');
        DB::table('scheduling_event_role_requirements')->where('crew_role_id', $oldRoleId)
            ->whereIn('scheduling_event_id', $eventIds)->update(['crew_role_id' => $newRoleId]);

        $shiftIds = DB::table('scheduling_shifts')->whereIn(
            'scheduling_event_id',
            DB::table('scheduling_events')->where('event_type', $eventType)->select('id'),
        )->select('id');
        DB::table('scheduling_shift_assignments')->where('crew_role_id', $oldRoleId)
            ->whereIn('scheduling_shift_id', $shiftIds)->update(['crew_role_id' => $newRoleId]);
    }
};
