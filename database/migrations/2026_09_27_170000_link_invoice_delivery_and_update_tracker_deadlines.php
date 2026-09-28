<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('studio_invoices', function (Blueprint $table) {
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::table('concert_tracker_email_deliveries', function (Blueprint $table) {
            $table->foreignId('studio_invoice_id')->nullable()->constrained()->nullOnDelete();
        });
        // Extend only deadlines last set by the email workflow, preserving manual changes.
        DB::table('concert_tracker_tasks')->where('task_key', 'final_numbers_received')->orderBy('id')->each(function ($task) {
            $change = DB::table('concert_tracker_task_changes')->where('concert_tracker_task_id', $task->id)->latest('id')->first();
            if ($change?->action !== 'email_deadline_set') {
                return;
            }
            $after = json_decode($change->after, true);
            $delivery = DB::table('concert_tracker_email_deliveries')->find($after['email_delivery_id'] ?? 0);
            if (! $delivery?->sent_at || $task->due_date_override !== Carbon::parse($delivery->sent_at)->addDays(7)->toDateString()) {
                return;
            }
            $due = Carbon::parse($delivery->sent_at)->addDays(10)->toDateString();
            DB::table('concert_tracker_tasks')->where('id', $task->id)->update(['due_date_override' => $due]);
            DB::table('concert_tracker_task_changes')->insert(['concert_tracker_task_id' => $task->id, 'action' => 'deadline_policy_updated',
                'before' => json_encode(['due_date_override' => $task->due_date_override]), 'after' => json_encode(['due_date_override' => $due]), 'created_at' => now()]);
        });
        DB::table('concert_tracker_email_templates')->where('task_key', 'invoice_sent')->update([
            'name' => 'Studio invoice & payment', 'subject' => 'Your DancePro studio invoice is ready',
            'body' => "Hi {{ contact_name }},\n\nYour invoice for **{{ studio_name }}** is now available in your Studio Hub.\n\n[VIEW AND PAY INVOICE]({{ invoice_url }})\n\nSign in to your studio account to view the invoice, download a PDF and pay. Payment is due 14 days after this invoice is first emailed; the invoice shows the due date and any payments received.\n\nThank you for booking DancePro Photography & Video.",
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('concert_tracker_email_deliveries', fn (Blueprint $table) => $table->dropConstrainedForeignId('studio_invoice_id'));
        Schema::table('studio_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sent_by_user_id');
            $table->dropColumn('sent_at');
        });
        // Preserve edited email text and audited deadlines on rollback.
    }
};
