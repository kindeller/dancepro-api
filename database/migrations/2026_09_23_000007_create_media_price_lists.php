<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_price_lists', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 120);
            $table->string('gallery_type', 20);
            $table->json('prices');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['gallery_type', 'is_default']);
        });

        Schema::table('scheduling_events', function (Blueprint $table): void {
            $table->foreignId('media_price_list_id')->nullable()->constrained()->nullOnDelete();
        });
        Schema::table('concerts', function (Blueprint $table): void {
            $table->foreignId('media_price_list_id')->nullable()->constrained()->nullOnDelete();
        });
        Schema::table('media_collections', function (Blueprint $table): void {
            $table->foreignId('media_price_list_id')->nullable()->constrained()->nullOnDelete();
        });
        Schema::table('programme_nodes', function (Blueprint $table): void {
            $table->foreignId('media_price_list_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('programme_nodes', fn (Blueprint $table) => $table->dropConstrainedForeignId('media_price_list_id'));
        Schema::table('media_collections', fn (Blueprint $table) => $table->dropConstrainedForeignId('media_price_list_id'));
        Schema::table('concerts', fn (Blueprint $table) => $table->dropConstrainedForeignId('media_price_list_id'));
        Schema::table('scheduling_events', fn (Blueprint $table) => $table->dropConstrainedForeignId('media_price_list_id'));
        Schema::dropIfExists('media_price_lists');
    }
};
