<?php

namespace Tests\Feature\Programmes;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class EventRoleMigrationTest extends TestCase
{
    public function test_migration_merges_role_identity_and_preserves_files_and_original_history(): void
    {
        $originalConnection = DB::getDefaultConnection();
        config(['database.connections.role_migration_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]]);
        DB::setDefaultConnection('role_migration_test');
        try {
            foreach (['users', 'crew_profiles', 'crew_roles', 'programme_upload_plans', 'programme_photo_galleries'] as $name) {
                Schema::create($name, fn (Blueprint $table) => $table->id());
                DB::table($name)->insert(['id' => 1]);
            }
            DB::table('crew_profiles')->insert(['id' => 2]);
            $contributions = require database_path('migrations/2026_09_21_000005_create_programme_photo_contributions_table.php');
            $contributions->up();
            $files = require database_path('migrations/2026_09_21_000006_create_programme_photo_files_table.php');
            $files->up();
            $completion = require database_path('migrations/2026_09_22_000002_add_photo_contribution_completion.php');
            $completion->up();
            Schema::table('programme_photo_files', function (Blueprint $table): void {
                $table->unsignedBigInteger('programme_photo_gallery_id')->nullable();
                $table->string('storage_disk')->nullable();
                $table->string('storage_key')->nullable();
                $table->timestamp('verified_at')->nullable();
            });
            $item = (string) Str::uuid();
            $client = (string) Str::uuid();
            $originalUuids = [];
            for ($i = 1; $i <= 2; $i++) {
                $uuid = (string) Str::uuid();
                $originalUuids[] = $uuid;
                DB::table('programme_photo_contributions')->insert([
                    'id' => $i, 'uuid' => $uuid, 'programme_upload_plan_id' => 1, 'item_uuid' => $item,
                    'crew_profile_id' => $i, 'crew_role_id' => 1, 'registered_by_user_id' => 1,
                    'assignment_uuids' => '[]', 'status' => 'complete', 'completed_at' => now(), 'completed_by_user_id' => 1,
                ]);
                DB::table('programme_photo_files')->insert([
                    'id' => $i, 'uuid' => (string) Str::uuid(), 'programme_photo_contribution_id' => $i,
                    'client_photo_uuid' => $client, 'file_name' => 'original.jpg', 'byte_count' => 100,
                    'sha256' => str_repeat('a', 64), 'is_top_pic' => true, 'status' => 'verified',
                    'verified_at' => now(), 'storage_disk' => 'local', 'storage_key' => "preserved/$i.jpg",
                ]);
            }
            $migration = require database_path('migrations/2026_09_22_000003_use_event_role_photo_contributions.php');
            $migration->up();
            $this->assertSame(1, DB::table('programme_photo_contributions')->count());
            $this->assertSame('complete', DB::table('programme_photo_contributions')->value('status'));
            $this->assertNull(DB::table('programme_photo_contributions')->value('crew_profile_id'));
            $this->assertSame(2, DB::table('programme_photo_contribution_history')->count());
            $this->assertSame(2, DB::table('programme_photo_files')->count());
            $this->assertSame(1, DB::table('programme_photo_files')->whereNull('duplicate_of_file_id')->count());
            $this->assertSame(['preserved/1.jpg', 'preserved/2.jpg'], DB::table('programme_photo_files')->orderBy('id')->pluck('storage_key')->all());
            $this->assertSame($originalUuids, DB::table('programme_photo_files')->orderBy('id')->pluck('original_contribution_uuid')->all());
            $this->assertSame([1], DB::table('programme_photo_files')->distinct()->pluck('programme_photo_contribution_id')->all());
            $this->assertSame(1, DB::table('programme_photo_files')->whereNotNull('reservation_key')->count());
            $this->assertSame(0, DB::table('programme_photo_files')->whereNotNull('uploaded_by_user_id')->count());
        } finally {
            DB::setDefaultConnection($originalConnection);
            DB::purge('role_migration_test');
        }
    }
}
