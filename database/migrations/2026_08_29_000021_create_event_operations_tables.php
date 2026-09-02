<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('venues', 'map_path')) {
            Schema::table('venues', function (Blueprint $table): void {
                $table->string('map_path')->nullable()->after('parking_notes');
            });
        }
        if (! Schema::hasColumn('scheduling_events', 'crew_brief')) {
            Schema::table('scheduling_events', function (Blueprint $table): void {
                $table->longText('crew_brief')->nullable()->after('admin_notes');
                $table->longText('team_leader_notes')->nullable()->after('crew_brief');
                $table->string('programme_path')->nullable()->after('team_leader_notes');
            });
        }

        if (! Schema::hasTable('operational_resources')) {
            Schema::create('operational_resources', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedTinyInteger('section_number')->nullable()->index();
                $table->string('title');
                $table->string('resource_type')->index();
                $table->string('event_type')->nullable()->index();
                $table->string('role_code')->nullable()->index();
                $table->text('summary')->nullable();
                $table->longText('content')->nullable();
                $table->string('file_path')->nullable();
                $table->string('external_url')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('checklist_templates')) {
            Schema::create('checklist_templates', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('name');
                $table->string('event_type')->nullable()->index();
                $table->string('role_code')->nullable()->index();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('checklist_template_items')) {
            Schema::create('checklist_template_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('checklist_template_id')->constrained()->cascadeOnDelete();
                $table->text('instruction');
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('assignment_checklist_completions')) {
            Schema::create('assignment_checklist_completions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('scheduling_shift_assignment_id')->constrained('scheduling_shift_assignments', indexName: 'checklist_completion_assignment_fk')->cascadeOnDelete();
                $table->foreignId('checklist_template_item_id')->constrained(indexName: 'checklist_completion_item_fk')->cascadeOnDelete();
                $table->foreignId('completed_by_user_id')->nullable()->constrained('users', indexName: 'checklist_completion_user_fk')->nullOnDelete();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->unique(['scheduling_shift_assignment_id', 'checklist_template_item_id'], 'assignment_checklist_item_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_checklist_completions');
        Schema::dropIfExists('checklist_template_items');
        Schema::dropIfExists('checklist_templates');
        Schema::dropIfExists('operational_resources');
        Schema::table('scheduling_events', fn (Blueprint $table) => $table->dropColumn(['crew_brief', 'team_leader_notes', 'programme_path']));
        Schema::table('venues', fn (Blueprint $table) => $table->dropColumn('map_path'));
    }
};
