<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crew_profiles', function (Blueprint $table): void {
            $table->text('bank_name')->nullable();
        });
        Schema::table('crew_invoices', function (Blueprint $table): void {
            $table->string('invoice_style')->default('classic');
        });
    }

    public function down(): void
    {
        Schema::table('crew_invoices', fn (Blueprint $table) => $table->dropColumn('invoice_style'));
        Schema::table('crew_profiles', fn (Blueprint $table) => $table->dropColumn('bank_name'));
    }
};
