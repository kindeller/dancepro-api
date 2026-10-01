<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_studios', function (Blueprint $table): void {
            $table->timestamp('membership_confirmed_at')->nullable();
        });
        Schema::create('customer_studio_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('name_key', 64);
            $table->string('location')->nullable();
            $table->string('status')->default('pending')->index();
            $table->foreignId('studio_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'name_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_studio_requests');
        Schema::table('customer_studios', fn (Blueprint $table) => $table->dropColumn('membership_confirmed_at'));
    }
};
