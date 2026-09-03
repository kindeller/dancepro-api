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
        foreach (['scheduling_shift_assignments', 'checklist_template_items'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->uuid('uuid')->nullable()->unique();
            });

            DB::table($tableName)->orderBy('id')->eachById(function (object $record) use ($tableName): void {
                DB::table($tableName)->where('id', $record->id)->update(['uuid' => (string) Str::uuid()]);
            });
        }
    }

    public function down(): void
    {
        foreach (['scheduling_shift_assignments', 'checklist_template_items'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->dropUnique($tableName.'_uuid_unique');
                $table->dropColumn('uuid');
            });
        }
    }
};
