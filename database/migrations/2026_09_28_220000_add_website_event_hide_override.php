<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scheduling_events', fn (Blueprint $table) => $table->boolean('website_hidden')->default(false)->index());
    }

    public function down(): void
    {
        Schema::table('scheduling_events', fn (Blueprint $table) => $table->dropColumn('website_hidden'));
    }
};
