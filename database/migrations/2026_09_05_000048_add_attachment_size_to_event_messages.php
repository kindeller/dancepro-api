<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_messages', function (Blueprint $table): void {
            $table->unsignedBigInteger('attachment_size')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('event_messages', function (Blueprint $table): void {
            $table->dropColumn('attachment_size');
        });
    }
};
