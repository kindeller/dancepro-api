<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competition_gallery_settings', function (Blueprint $table): void {
            $table->string('login_code_hash')->nullable();
        });
        Schema::table('customer_competition_registrations', function (Blueprint $table): void {
            $table->timestamp('access_verified_at')->nullable();
            $table->dropColumn('registered_code');
        });
    }

    public function down(): void
    {
        Schema::table('customer_competition_registrations', function (Blueprint $table): void {
            $table->string('registered_code', 100)->nullable();
            $table->dropColumn('access_verified_at');
        });
        Schema::table('competition_gallery_settings', function (Blueprint $table): void {
            $table->dropColumn('login_code_hash');
        });
    }
};
