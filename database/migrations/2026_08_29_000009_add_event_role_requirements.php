<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduling_event_role_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheduling_event_id')->constrained('scheduling_events')->cascadeOnDelete();
            $table->foreignId('crew_role_id')->constrained('crew_roles')->restrictOnDelete();
            $table->unsignedTinyInteger('quantity')->default(1);
            $table->timestamps();
            $table->unique(['scheduling_event_id', 'crew_role_id'], 'event_role_requirement_unique');
        });

        DB::table('scheduling_event_role_requirements')->insertUsing(
            ['scheduling_event_id', 'crew_role_id', 'quantity', 'created_at', 'updated_at'],
            DB::table('scheduling_shift_role_requirements')
                ->join('scheduling_shifts', 'scheduling_shifts.id', '=', 'scheduling_shift_role_requirements.scheduling_shift_id')
                ->select([
                    'scheduling_shifts.scheduling_event_id',
                    'scheduling_shift_role_requirements.crew_role_id',
                    DB::raw('MAX(scheduling_shift_role_requirements.quantity)'),
                    DB::raw('NOW()'),
                    DB::raw('NOW()'),
                ])
                ->groupBy('scheduling_shifts.scheduling_event_id', 'scheduling_shift_role_requirements.crew_role_id'),
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduling_event_role_requirements');
    }
};
