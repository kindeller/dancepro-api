<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('concert_title_card_templates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('scheduling_event_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('logo_source', 20)->default('event');
            $table->string('background_color', 7)->default('#000000');
            $table->string('logo_band_color', 7)->default('#0B438C');
            $table->string('text_color', 7)->default('#FFFFFF');
            $table->string('separator_start_color', 7)->default('#000000');
            $table->string('separator_end_color', 7)->default('#FFFFFF');
            $table->string('font_family', 80)->default('Atlanta');
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('concert_title_card_templates');
    }
};
