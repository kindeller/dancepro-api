<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            foreach (DB::table('assignment_equipment_responsibilities')->where('item_code', 'media')->get() as $item) {
                foreach (['photos', 'videos'] as $code) {
                    $this->merge($item, $code);
                }
                DB::table('assignment_equipment_responsibilities')->where('id', $item->id)->delete();
            }
        });
    }

    public function down(): void
    {
        // Rollback preserves directions and notes, but combines the two media types.
        DB::transaction(function (): void {
            foreach (DB::table('assignment_equipment_responsibilities')->whereIn('item_code', ['photos', 'videos'])->get() as $item) {
                $this->merge($item, 'media');
                DB::table('assignment_equipment_responsibilities')->where('id', $item->id)->delete();
            }
        });
    }

    private function merge(object $item, string $code): void
    {
        $key = ['scheduling_shift_assignment_id' => $item->scheduling_shift_assignment_id, 'item_code' => $code];
        $existing = DB::table('assignment_equipment_responsibilities')->where($key)->first();
        DB::table('assignment_equipment_responsibilities')->updateOrInsert($key, [
            'is_bringing' => $item->is_bringing || ($existing?->is_bringing ?? false),
            'is_taking' => $item->is_taking || ($existing?->is_taking ?? false),
            'other_notes' => collect([$existing?->other_notes, $item->other_notes])->filter()->unique()->join(' · ') ?: null,
            'created_at' => $existing?->created_at ?? $item->created_at,
            'updated_at' => $item->updated_at,
        ]);
    }
};
