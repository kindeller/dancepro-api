<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('customer_account_emails')->where('metadata->kind', 'studio_payment_received')
            ->select('id')->orderBy('id')->chunkById(100, function ($emails): void {
                DB::table('customer_inbox_notifications')
                    ->whereIn('source_key', $emails->map(fn ($email) => 'account:'.$email->id)->all())->delete();
            });
    }

    public function down(): void
    {
        // Do not recreate misplaced personal notifications. Email audit and
        // studio messages, payment history and receipt snapshots are retained.
    }
};
