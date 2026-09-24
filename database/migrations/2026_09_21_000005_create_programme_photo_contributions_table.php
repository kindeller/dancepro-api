<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programme_photo_contributions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('programme_upload_plan_id')->constrained()->restrictOnDelete();
            $table->uuid('item_uuid');
            $table->foreignId('crew_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('crew_role_id')->constrained()->restrictOnDelete();
            $table->foreignId('registered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('assignment_uuids');
            $table->string('status')->default('awaiting_files');
            $table->timestamps();
            $table->unique(['programme_upload_plan_id', 'item_uuid', 'crew_profile_id', 'crew_role_id'], 'programme_photo_contribution_identity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programme_photo_contributions');
    }
};
