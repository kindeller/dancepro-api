<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pay_rates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('rate_key')->index();
            $table->string('name');
            $table->string('calculation_type');
            $table->decimal('amount', 10, 2);
            $table->boolean('is_superable')->default(true);
            $table->date('effective_from')->index();
            $table->date('effective_until')->nullable()->index();
            $table->timestamps();
            $table->unique(['rate_key', 'effective_from']);
        });

        Schema::create('assignment_allowances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheduling_shift_assignment_id')->constrained('scheduling_shift_assignments', indexName: 'assignment_allowance_assignment_fk')->cascadeOnDelete();
            $table->string('allowance_key');
            $table->unsignedTinyInteger('quantity')->default(1);
            $table->timestamps();
            $table->unique(['scheduling_shift_assignment_id', 'allowance_key'], 'assignment_allowance_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_allowances');
        Schema::dropIfExists('pay_rates');
    }
};
