<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['scheduling_events', 'concert_booking_items', 'crew_roles', 'operational_resources', 'checklist_templates'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('event_type_definition_id')->nullable()->constrained()->nullOnDelete();
            });
        }

        foreach (['scheduling_events', 'crew_roles', 'operational_resources', 'checklist_templates'] as $tableName) {
            DB::table($tableName)->where('event_type', 'concert')->update(['event_type_definition_id' => DB::table('event_type_definitions')->where('code', 'concert')->value('id')]);
            DB::table($tableName)->where('event_type', 'competition')->update(['event_type_definition_id' => DB::table('event_type_definitions')->where('code', 'competition')->value('id')]);
        }
        DB::table('concert_booking_items')->update(['event_type_definition_id' => DB::table('event_type_definitions')->where('code', 'concert')->value('id')]);
    }

    public function down(): void
    {
        foreach (['scheduling_events', 'concert_booking_items', 'crew_roles', 'operational_resources', 'checklist_templates'] as $tableName) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropConstrainedForeignId('event_type_definition_id'));
        }
    }
};
