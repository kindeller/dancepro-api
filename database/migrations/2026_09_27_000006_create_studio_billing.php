<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studio_billing_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('business_name')->nullable();
            $table->string('abn', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('email')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_bsb', 20)->nullable();
            $table->string('bank_account', 30)->nullable();
            $table->timestamps();
        });
        Schema::create('studio_invoices', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('studio_id')->constrained()->restrictOnDelete();
            $table->foreignId('concert_booking_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('active_booking_id')->nullable()->unique();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('draft');
            $table->boolean('is_test')->default(false);
            $table->json('issuer')->nullable();
            $table->json('bill_to');
            $table->json('lines');
            $table->unsignedBigInteger('total_cents');
            $table->unsignedBigInteger('gst_cents');
            $table->string('currency', 3)->default('AUD');
            $table->string('source_hash', 64);
            $table->text('notes')->nullable();
            $table->date('due_date');
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();
        });
        Schema::create('studio_invoice_payments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('idempotency_key')->unique();
            $table->foreignId('studio_invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 30);
            $table->string('status', 30);
            $table->boolean('is_test')->default(false);
            $table->unsignedBigInteger('amount_cents');
            $table->string('currency', 3)->default('AUD');
            $table->string('provider_transaction_id')->nullable()->unique();
            $table->string('reference')->nullable();
            $table->text('message')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('studio_invoice_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('studio_invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->json('details')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_invoice_events');
        Schema::dropIfExists('studio_invoice_payments');
        Schema::dropIfExists('studio_invoices');
        Schema::dropIfExists('studio_billing_settings');
    }
};
