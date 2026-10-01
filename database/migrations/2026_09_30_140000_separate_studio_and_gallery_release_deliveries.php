<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('concert_tracker_email_deliveries', 'photo_release_mail_kind')) {
            Schema::table('concert_tracker_email_deliveries', function (Blueprint $table): void {
                $table->string('photo_release_mail_kind', 16)->default('gallery');
            });
        }
        DB::table('concert_tracker_email_deliveries')->whereNotNull('photo_release_group_id')
            ->where('workflow_context->photo_release_audience', 'studio')->update(['photo_release_mail_kind' => 'studio']);
        Schema::table('concert_tracker_email_deliveries', function (Blueprint $table): void {
            $table->unique(['photo_release_group_id', 'recipient_email', 'photo_release_mail_kind'], 'photo_release_recipient_kind_unique');
        });
        // Keep an index supporting the release-group foreign key throughout the change.
        Schema::table('concert_tracker_email_deliveries', function (Blueprint $table): void {
            $table->dropUnique('photo_release_recipient_unique');
        });
    }

    public function down(): void
    {
        if (DB::table('concert_tracker_email_deliveries')->whereNotNull('photo_release_group_id')
            ->select('photo_release_group_id', 'recipient_email')->groupBy('photo_release_group_id', 'recipient_email')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Cannot restore the old release email key while separate gallery and studio deliveries exist. Delivery history has been preserved.');
        }
        Schema::table('concert_tracker_email_deliveries', function (Blueprint $table): void {
            $table->unique(['photo_release_group_id', 'recipient_email'], 'photo_release_recipient_unique');
        });
        Schema::table('concert_tracker_email_deliveries', function (Blueprint $table): void {
            $table->dropUnique('photo_release_recipient_kind_unique');
            $table->dropColumn('photo_release_mail_kind');
        });
    }
};
