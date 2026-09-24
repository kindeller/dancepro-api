<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competition_programme_additions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('programme_upload_plan_id')->constrained()->cascadeOnDelete();
            $table->uuid('node_uuid');
            $table->json('node');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['programme_upload_plan_id', 'node_uuid'], 'competition_addition_identity');
        });
        Schema::create('competition_folder_inventories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('programme_upload_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crew_role_id')->constrained();
            $table->uuid('device_uuid');
            $table->json('folders');
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['programme_upload_plan_id', 'crew_role_id', 'device_uuid'], 'competition_inventory_station');
        });
    }

    public function down(): void
    {
        // These additions may own uploaded media; never orphan those identities.
        if (DB::table('competition_programme_additions')->exists()) {
            throw new RuntimeException('Competition additions exist. Preserve their identities with a coordinated database restore instead of rolling back.');
        }
        Schema::dropIfExists('competition_folder_inventories');
        Schema::dropIfExists('competition_programme_additions');
    }
};
