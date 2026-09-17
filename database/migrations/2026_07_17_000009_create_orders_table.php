<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('customer_email')->nullable()->index();
            $table->string('customer_name')->nullable();
            $table->string('status')->default('draft')->index();
            $table->char('currency', 3)->default('AUD');
            $table->unsignedBigInteger('subtotal_amount')->default(0);
            $table->unsignedBigInteger('total_amount')->default(0);
            $table->timestamp('placed_at')->nullable()->index();
            $table->timestamp('paid_at')->nullable()->index();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
