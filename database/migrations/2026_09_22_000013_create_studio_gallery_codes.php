<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('studios', function (Blueprint $table): void {
            $table->string('gallery_code_hash')->nullable();
            $table->unsignedInteger('gallery_code_version')->default(0);
        });
        Schema::table('customer_studios', fn (Blueprint $table) => $table->timestamp('access_verified_at')->nullable());
        Schema::create('studio_gallery_code_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('studio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_gallery_code_changes');
        Schema::table('customer_studios', fn (Blueprint $table) => $table->dropColumn('access_verified_at'));
        Schema::table('studios', fn (Blueprint $table) => $table->dropColumn(['gallery_code_hash', 'gallery_code_version']));
    }
};
