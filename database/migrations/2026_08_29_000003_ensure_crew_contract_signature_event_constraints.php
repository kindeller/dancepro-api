<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = 'crew_contract_signature_events';
        $foreignKeys = collect(Schema::getForeignKeys($table));

        Schema::table($table, function (Blueprint $blueprint) use ($foreignKeys, $table) {
            if (! $foreignKeys->contains(fn (array $key): bool => $key['columns'] === ['crew_contract_signature_id'])) {
                $blueprint->foreign('crew_contract_signature_id', 'crew_signature_event_signature_fk')
                    ->references('id')
                    ->on('crew_contract_signatures')
                    ->cascadeOnDelete();
            }

            if (! $foreignKeys->contains(fn (array $key): bool => $key['columns'] === ['recorded_by_user_id'])) {
                $blueprint->foreign('recorded_by_user_id', 'crew_signature_event_recorder_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasIndex($table, ['recording_method'])) {
                $blueprint->index('recording_method', 'crew_signature_event_method_idx');
            }
        });
    }

    public function down(): void
    {
        // The constraints are part of the table's required schema. The table is
        // removed by the preceding crew contract migration during full rollback.
    }
};
