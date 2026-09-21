<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('concerts', function (Blueprint $table): void {
            $table->text('access_password_encrypted')->nullable()->after('access_password_hash');
        });
    }

    public function down(): void
    {
        Schema::table('concerts', function (Blueprint $table): void {
            $table->dropColumn('access_password_encrypted');
        });
    }
};
