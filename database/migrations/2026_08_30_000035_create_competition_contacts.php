<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competition_contacts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name')->index();
            $table->string('organiser_name');
            $table->string('organiser_email')->index();
            $table->string('organiser_phone', 50);
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('scheduling_events', function (Blueprint $table): void {
            $table->foreignId('competition_contact_id')->nullable()->after('venue_id')->constrained('competition_contacts')->nullOnDelete();
            $table->string('organiser_email')->nullable()->after('organiser_name');
        });
    }

    public function down(): void
    {
        Schema::table('scheduling_events', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('competition_contact_id');
            $table->dropColumn('organiser_email');
        });
        Schema::dropIfExists('competition_contacts');
    }
};
