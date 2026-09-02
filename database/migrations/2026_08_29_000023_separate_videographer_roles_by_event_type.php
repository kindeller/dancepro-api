<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $oldRole = DB::table('crew_roles')->where('code', 'videographer')->first();
        $competitionRoleId = $this->role('competition-videographer', 'Competition Videographer V', 'competition');
        $concertRoleId = $this->role('concert-videographer', 'Concert Videographer V', 'concert');

        if ($oldRole) {
            $this->copyQualifications($oldRole->id, [$competitionRoleId, $concertRoleId]);
            $this->moveReferencesForType($oldRole->id, $competitionRoleId, 'competition');
            $this->moveReferencesForType($oldRole->id, $concertRoleId, 'concert');
            DB::table('crew_roles')->where('id', $oldRole->id)->update(['is_active' => false]);
        }

        DB::table('operational_resources')->where('role_code', 'videographer')->where('event_type', 'competition')->update(['role_code' => 'competition-videographer']);
        DB::table('operational_resources')->where('role_code', 'videographer')->where('event_type', 'concert')->update(['role_code' => 'concert-videographer']);
        DB::table('checklist_templates')->where('role_code', 'videographer')->where('event_type', 'competition')->update(['role_code' => 'competition-videographer']);
        DB::table('checklist_templates')->where('role_code', 'videographer')->where('event_type', 'concert')->update(['role_code' => 'concert-videographer']);
    }

    public function down(): void
    {
        // Event-specific roles cannot be merged safely after new assignments are made.
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
        DB::table('scheduling_event_role_requirements')->where('crew_role_id', $oldRoleId)
            ->whereIn('scheduling_event_id', DB::table('scheduling_events')->where('event_type', $eventType)->select('id'))
            ->update(['crew_role_id' => $newRoleId]);

        DB::table('scheduling_shift_assignments')->where('crew_role_id', $oldRoleId)
            ->whereIn('scheduling_shift_id', DB::table('scheduling_shifts')->whereIn('scheduling_event_id', DB::table('scheduling_events')->where('event_type', $eventType)->select('id'))->select('id'))
            ->update(['crew_role_id' => $newRoleId]);
    }
};
