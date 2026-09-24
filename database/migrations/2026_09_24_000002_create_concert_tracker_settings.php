<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('concert_tracker_automation_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('task_key', 80)->unique();
            $table->string('mode', 20)->default('review');
            $table->integer('offset_days')->default(0);
            $table->time('send_time')->default('09:00');
            $table->timestamps();
        });
        Schema::create('concert_tracker_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unassigned_alert_user_id')->nullable()->constrained('users', indexName: 'tracker_setting_alert_user_fk')->nullOnDelete();
            $table->unsignedSmallInteger('escalation_after_days')->default(2);
            $table->unsignedSmallInteger('editing_target_days')->default(14);
            $table->unsignedSmallInteger('approval_target_days')->default(14);
            $table->timestamps();
        });
        $now = now();
        foreach ([
            ['confirmation_information_email', 'review', 0, '09:00'],
            ['final_numbers_email', 'review', -30, '09:00'],
            ['concert_week_email', 'review', -7, '09:00'],
            ['post_concert_email', 'review', 1, '09:00'],
        ] as [$taskKey, $mode, $offset, $time]) {
            DB::table('concert_tracker_automation_rules')->insert(['task_key' => $taskKey, 'mode' => $mode, 'offset_days' => $offset, 'send_time' => $time, 'created_at' => $now, 'updated_at' => $now]);
        }
        DB::table('concert_tracker_settings')->insert(['escalation_after_days' => 2, 'editing_target_days' => 14, 'approval_target_days' => 14, 'created_at' => $now, 'updated_at' => $now]);
    }

    public function down(): void
    {
        Schema::dropIfExists('concert_tracker_settings');
        Schema::dropIfExists('concert_tracker_automation_rules');
    }
};
