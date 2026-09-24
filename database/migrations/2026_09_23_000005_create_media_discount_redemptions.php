<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_discount_codes', function (Blueprint $table): void {
            $table->unsignedInteger('max_redemptions')->nullable()->after('enabled');
            $table->unsignedSmallInteger('max_redemptions_per_customer')->nullable()->after('max_redemptions');
        });
        Schema::create('media_discount_redemptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_discount_code_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete()->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 32);
            $table->unsignedTinyInteger('percent');
            $table->unsignedBigInteger('amount');
            $table->timestamp('redeemed_at');
            $table->timestamps();

            $table->index(['media_discount_code_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_discount_redemptions');
        Schema::table('media_discount_codes', function (Blueprint $table): void {
            $table->dropColumn(['max_redemptions', 'max_redemptions_per_customer']);
        });
    }
};
