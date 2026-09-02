<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crew_contract_signatures', function (Blueprint $table): void {
            $table->string('signed_name')->nullable()->after('signed_at');
            $table->string('signer_ip', 45)->nullable()->after('signed_name');
            $table->text('signer_user_agent')->nullable()->after('signer_ip');
            $table->string('contract_checksum', 64)->nullable()->after('signer_user_agent');
            $table->text('consent_text')->nullable()->after('contract_checksum');
        });

        Schema::table('crew_contract_signature_events', function (Blueprint $table): void {
            $table->string('signed_name')->nullable()->after('new_signed_at');
            $table->string('signer_ip', 45)->nullable()->after('signed_name');
            $table->text('signer_user_agent')->nullable()->after('signer_ip');
            $table->string('contract_checksum', 64)->nullable()->after('signer_user_agent');
            $table->text('consent_text')->nullable()->after('contract_checksum');
        });
    }

    public function down(): void
    {
        Schema::table('crew_contract_signature_events', function (Blueprint $table): void {
            $table->dropColumn(['signed_name', 'signer_ip', 'signer_user_agent', 'contract_checksum', 'consent_text']);
        });
        Schema::table('crew_contract_signatures', function (Blueprint $table): void {
            $table->dropColumn(['signed_name', 'signer_ip', 'signer_user_agent', 'contract_checksum', 'consent_text']);
        });
    }
};
