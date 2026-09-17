<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_collections', function (Blueprint $table): void {
            $table->foreignId('created_by_user_id')->nullable()->after('manifest_key')->constrained('users')->nullOnDelete();
            $table->uuid('idempotency_key')->nullable()->after('created_by_user_id');
            $table->unique(['created_by_user_id', 'idempotency_key'], 'media_collections_creator_idempotency_unique');
        });

        Schema::table('media_assets', function (Blueprint $table): void {
            $table->foreignId('created_by_user_id')->nullable()->after('thumbnail_storage_key')->constrained('users')->nullOnDelete();
            $table->uuid('idempotency_key')->nullable()->after('created_by_user_id');
            $table->unique(['created_by_user_id', 'idempotency_key'], 'media_assets_creator_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->dropUnique('media_assets_creator_idempotency_unique');
            $table->dropConstrainedForeignId('created_by_user_id');
            $table->dropColumn('idempotency_key');
        });

        Schema::table('media_collections', function (Blueprint $table): void {
            $table->dropUnique('media_collections_creator_idempotency_unique');
            $table->dropConstrainedForeignId('created_by_user_id');
            $table->dropColumn('idempotency_key');
        });
    }
};
