<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_modules', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable()->unique()->after('id');
        });

        DB::table('training_modules')->orderBy('id')->get(['id'])->each(
            fn (object $module) => DB::table('training_modules')->where('id', $module->id)->update(['uuid' => (string) Str::uuid()]),
        );
    }

    public function down(): void
    {
        Schema::table('training_modules', function (Blueprint $table): void {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });
    }
};
