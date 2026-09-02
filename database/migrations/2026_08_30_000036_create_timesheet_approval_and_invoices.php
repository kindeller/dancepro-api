<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignment_time_entries', function (Blueprint $table): void {
            $table->string('approval_status')->default('draft')->index();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('return_note')->nullable();
            $table->timestamp('locked_at')->nullable();
        });

        Schema::create('crew_invoices', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('crew_profile_id')->constrained('crew_profiles')->restrictOnDelete();
            $table->string('invoice_number')->nullable()->unique();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status')->default('draft')->index();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('allowance_total', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->decimal('superable_total', 10, 2)->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('exported_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('crew_invoice_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('crew_invoice_id')->constrained('crew_invoices')->cascadeOnDelete();
            $table->foreignId('assignment_time_entry_id')->unique()->constrained('assignment_time_entries')->restrictOnDelete();
            $table->json('snapshot');
            $table->decimal('base_amount', 10, 2);
            $table->decimal('allowance_amount', 10, 2)->default(0);
            $table->decimal('line_total', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crew_invoice_lines');
        Schema::dropIfExists('crew_invoices');
        Schema::table('assignment_time_entries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('approved_by_user_id');
            $table->dropColumn(['approval_status', 'submitted_at', 'approved_at', 'return_note', 'locked_at']);
        });
    }
};
