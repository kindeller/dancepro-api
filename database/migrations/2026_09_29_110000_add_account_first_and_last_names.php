<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
        });
        DB::table('users')->select('id', 'name')->orderBy('id')->chunkById(200, function ($users): void {
            foreach ($users as $user) {
                $parts = preg_split('/\s+/u', trim((string) $user->name), 2) ?: [];
                DB::table('users')->where('id', $user->id)->update(['first_name' => $parts[0] ?? '', 'last_name' => $parts[1] ?? null]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['first_name', 'last_name']));
    }
};
