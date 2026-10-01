<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('studio_invoice_payments', fn (Blueprint $table) => $table->json('receipt')->nullable());
    }

    public function down(): void
    {
        Schema::table('studio_invoice_payments', fn (Blueprint $table) => $table->dropColumn('receipt'));
    }
};
