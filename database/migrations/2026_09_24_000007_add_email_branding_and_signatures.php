<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('concert_tracker_settings', function (Blueprint $table): void {
            $table->string('email_business_name')->default('DancePro Photography & Video');
            $table->string('email_logo_url')->nullable();
            $table->string('email_phone')->nullable();
            $table->string('email_address')->nullable();
            $table->string('email_website')->default('https://www.dancepro.com.au');
            $table->string('email_reply_address')->nullable();
            $table->text('email_legal_footer')->nullable();
        });
        Schema::create('staff_email_signatures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('display_name');
            $table->string('position')->nullable();
            $table->string('phone')->nullable();
            $table->text('sign_off')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_email_signatures');
        Schema::table('concert_tracker_settings', function (Blueprint $table): void {
            $table->dropColumn(['email_business_name', 'email_logo_url', 'email_phone', 'email_address', 'email_website', 'email_reply_address', 'email_legal_footer']);
        });
    }
};
