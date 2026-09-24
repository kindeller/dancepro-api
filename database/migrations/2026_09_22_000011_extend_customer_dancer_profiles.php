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
        Schema::table('customer_dancers', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable()->unique();
            $table->string('relationship', 30)->default('unspecified');
            $table->json('programme_names')->nullable();
        });
        DB::table('customer_dancers')->orderBy('id')->chunkById(100, function ($dancers): void {
            foreach ($dancers as $dancer) {
                DB::table('customer_dancers')->where('id', $dancer->id)->update(['uuid' => (string) Str::uuid()]);
            }
        });
        Schema::table('customer_dancers', fn (Blueprint $table) => $table->uuid('uuid')->nullable(false)->change());
    }

    public function down(): void
    {
        Schema::table('customer_dancers', function (Blueprint $table): void {
            $table->dropUnique(['uuid']);
            $table->dropColumn(['uuid', 'relationship', 'programme_names']);
        });
    }
};
