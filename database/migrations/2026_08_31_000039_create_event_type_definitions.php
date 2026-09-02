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
        Schema::create('event_type_definitions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('system_category');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        $now = now();
        DB::table('event_type_definitions')->insert([
            ['uuid' => (string) Str::uuid(), 'code' => 'concert', 'name' => 'Concert', 'system_category' => 'concert', 'description' => 'Concerts, dress rehearsals and related studio events.', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['uuid' => (string) Str::uuid(), 'code' => 'competition', 'name' => 'Competition', 'system_category' => 'competition', 'description' => 'Multi-session dance competitions.', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('event_type_definitions');
    }
};
