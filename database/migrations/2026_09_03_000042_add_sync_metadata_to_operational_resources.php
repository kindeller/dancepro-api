<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operational_resources', function (Blueprint $table): void {
            $table->string('file_mime_type')->nullable()->after('file_path');
            $table->unsignedBigInteger('file_size')->nullable()->after('file_mime_type');
            $table->string('file_checksum', 64)->nullable()->after('file_size');
        });
    }

    public function down(): void
    {
        Schema::table('operational_resources', function (Blueprint $table): void {
            $table->dropColumn(['file_mime_type', 'file_size', 'file_checksum']);
        });
    }
};
