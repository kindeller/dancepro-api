<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_discount_codes', function (Blueprint $table): void {
            $table->string('discount_type', 16)->default('percent')->after('code');
            $table->unsignedBigInteger('amount')->nullable()->after('percent');
            $table->unsignedTinyInteger('percent')->nullable()->change();
        });
        Schema::table('media_discount_redemptions', function (Blueprint $table): void {
            $table->string('discount_type', 16)->default('percent')->after('code');
            $table->unsignedBigInteger('configured_amount')->nullable()->after('percent');
            $table->unsignedTinyInteger('percent')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('media_discount_redemptions')->whereNull('percent')->update(['percent' => 1]);
        Schema::table('media_discount_redemptions', function (Blueprint $table): void {
            $table->unsignedTinyInteger('percent')->nullable(false)->change();
            $table->dropColumn(['discount_type', 'configured_amount']);
        });
        DB::table('media_discount_codes')->whereNull('percent')->update(['percent' => 1]);
        Schema::table('media_discount_codes', function (Blueprint $table): void {
            $table->unsignedTinyInteger('percent')->nullable(false)->change();
            $table->dropColumn(['discount_type', 'amount']);
        });
    }
};
