<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crew_invoices', function (Blueprint $table): void {
            $table->text('issuer_snapshot')->nullable()->after('invoice_style');
        });
    }

    public function down(): void
    {
        Schema::table('crew_invoices', fn (Blueprint $table) => $table->dropColumn('issuer_snapshot'));
    }
};
