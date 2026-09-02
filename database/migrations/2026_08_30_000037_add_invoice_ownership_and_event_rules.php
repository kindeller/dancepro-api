<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crew_profiles', function (Blueprint $table): void {
            $table->unsignedBigInteger('next_invoice_number')->nullable();
        });

        Schema::table('crew_invoices', function (Blueprint $table): void {
            $table->dropUnique(['invoice_number']);
            $table->foreignId('scheduling_event_id')->nullable()->after('crew_profile_id')->constrained('scheduling_events')->restrictOnDelete();
            $table->string('source')->default('dancepro')->after('scheduling_event_id');
            $table->unique(['crew_profile_id', 'invoice_number']);
        });
    }

    public function down(): void
    {
        Schema::table('crew_invoices', function (Blueprint $table): void {
            $table->dropUnique(['crew_profile_id', 'invoice_number']);
            $table->dropConstrainedForeignId('scheduling_event_id');
            $table->dropColumn('source');
            $table->unique('invoice_number');
        });
        Schema::table('crew_profiles', fn (Blueprint $table) => $table->dropColumn('next_invoice_number'));
    }
};
