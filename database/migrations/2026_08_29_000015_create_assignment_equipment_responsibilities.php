<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignment_equipment_responsibilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheduling_shift_assignment_id')
                ->constrained('scheduling_shift_assignments', indexName: 'equipment_responsibility_assignment_fk')
                ->cascadeOnDelete();
            $table->string('item_code')->index();
            $table->boolean('is_bringing')->default(false);
            $table->boolean('is_taking')->default(false);
            $table->timestamps();
            $table->unique(['scheduling_shift_assignment_id', 'item_code'], 'assignment_equipment_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_equipment_responsibilities');
    }
};
