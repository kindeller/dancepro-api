<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crew_contracts')) {
            Schema::create('crew_contracts', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('name');
                $table->string('version');
                $table->string('status')->default('draft')->index();
                $table->date('effective_from')->nullable()->index();
                $table->longText('content')->nullable();
                $table->string('document_disk')->nullable();
                $table->string('document_path')->nullable();
                $table->string('document_checksum', 64)->nullable();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['name', 'version']);
            });
        }

        if (! Schema::hasTable('crew_contract_signatures')) {
            Schema::create('crew_contract_signatures', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('crew_contract_id')->constrained('crew_contracts')->restrictOnDelete();
                $table->foreignId('crew_profile_id')->constrained('crew_profiles')->cascadeOnDelete();
                $table->string('status')->default('pending')->index();
                $table->timestamp('signed_at')->nullable()->index();
                $table->string('recording_method')->nullable()->index();
                $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('recorded_at')->nullable();
                $table->text('recording_note')->nullable();
                $table->timestamps();

                $table->unique(['crew_contract_id', 'crew_profile_id']);
            });
        }

        if (! Schema::hasTable('crew_contract_signature_events')) {
            Schema::create('crew_contract_signature_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('crew_contract_signature_id')
                    ->constrained('crew_contract_signatures', indexName: 'crew_signature_event_signature_fk')
                    ->cascadeOnDelete();
                $table->string('previous_status')->nullable();
                $table->timestamp('previous_signed_at')->nullable();
                $table->string('new_status');
                $table->timestamp('new_signed_at')->nullable();
                $table->string('recording_method')->index();
                $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('recorded_at');
                $table->text('recording_note')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crew_contract_signature_events');
        Schema::dropIfExists('crew_contract_signatures');
        Schema::dropIfExists('crew_contracts');
    }
};
