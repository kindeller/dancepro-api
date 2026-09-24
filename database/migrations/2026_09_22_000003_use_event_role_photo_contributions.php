<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programme_photo_contribution_history', function (Blueprint $table): void {
            $table->id();
            $table->uuid('original_uuid')->unique();
            $table->uuid('canonical_uuid');
            $table->json('snapshot');
        });
        Schema::create('programme_upload_audits', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_uuid');
            $table->uuid('role_uuid');
            $table->uuid('file_uuid')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->json('details');
            $table->timestamp('created_at');
        });
        Schema::table('programme_photo_files', function (Blueprint $table): void {
            $table->index('programme_photo_contribution_id', 'photo_file_contribution_lookup');
            $table->dropUnique('programme_photo_file_identity');
            $table->char('reservation_key', 64)->nullable()->unique();
            $table->uuid('original_contribution_uuid')->nullable();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('duplicate_of_file_id')->nullable()->constrained('programme_photo_files')->restrictOnDelete();
        });
        Schema::table('programme_photo_contributions', function (Blueprint $table): void {
            $table->index('programme_upload_plan_id', 'photo_contribution_plan_lookup');
            $table->dropUnique('programme_photo_contribution_identity');
            $table->unsignedBigInteger('crew_profile_id')->nullable()->change();
        });
        DB::transaction(function (): void {
            $groups = DB::table('programme_photo_contributions')->orderBy('id')->get()
                ->groupBy(fn ($row) => $row->programme_upload_plan_id.':'.$row->item_uuid.':'.$row->crew_role_id);
            foreach ($groups as $rows) {
                $canonical = $rows->first();
                $allComplete = $rows->every(fn ($row) => $row->status === 'complete' && $row->completed_at !== null);
                foreach ($rows as $row) {
                    DB::table('programme_photo_contribution_history')->insert([
                        'original_uuid' => $row->uuid, 'canonical_uuid' => $canonical->uuid,
                        'snapshot' => json_encode($row, JSON_THROW_ON_ERROR),
                    ]);
                    DB::table('programme_photo_files')->where('programme_photo_contribution_id', $row->id)
                        ->update(['programme_photo_contribution_id' => $canonical->id, 'original_contribution_uuid' => $row->uuid]);
                }
                $files = DB::table('programme_photo_files')->where('programme_photo_contribution_id', $canonical->id)->orderBy('id')->get();
                $keys = [];
                $contents = [];
                foreach ($files as $file) {
                    if ($file->status === 'verified' && $file->verified_at !== null) {
                        $content = $file->sha256.':'.$file->byte_count;
                        if (isset($contents[$content])) {
                            DB::table('programme_photo_files')->where('id', $file->id)->update(['duplicate_of_file_id' => $contents[$content]]);
                        } else {
                            $contents[$content] = $file->id;
                        }
                    }
                    $key = hash('sha256', $canonical->id.':'.$file->client_photo_uuid.':'.$file->sha256);
                    if (! isset($keys[$key])) {
                        DB::table('programme_photo_files')->where('id', $file->id)->update(['reservation_key' => $key]);
                        $keys[$key] = true;
                    }
                }
                $allComplete = $allComplete && $files->every(fn ($file) => $file->status === 'verified' && $file->verified_at !== null);
                DB::table('programme_photo_contributions')->where('id', $canonical->id)->update([
                    'crew_profile_id' => null, 'assignment_uuids' => '[]',
                    'status' => $allComplete ? 'complete' : ($files->isEmpty() ? 'awaiting_files' : 'receiving'),
                    'completed_at' => $allComplete ? $canonical->completed_at : null,
                    'completed_by_user_id' => $allComplete ? $canonical->completed_by_user_id : null,
                ]);
                DB::table('programme_photo_contributions')->whereIn('id', $rows->pluck('id')->reject(fn ($id) => $id === $canonical->id))->delete();
            }
        });
        Schema::table('programme_photo_contributions', function (Blueprint $table): void {
            $table->unique(['programme_upload_plan_id', 'item_uuid', 'crew_role_id'], 'programme_photo_role_identity');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Event-role uploads cannot be safely reassigned to individuals automatically. Restore a coordinated database backup instead. No photo files are deleted by this migration.');
    }
};
