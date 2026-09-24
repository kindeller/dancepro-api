<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('concert_tracker_tasks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('scope_type', 32);
            $table->unsignedBigInteger('scope_id');
            $table->string('task_key', 80);
            $table->string('status', 32)->default('not_started');
            $table->foreignId('assignee_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date_override')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['scope_type', 'scope_id', 'task_key'], 'concert_tracker_task_scope_unique');
            $table->index(['task_key', 'status']);
        });

        Schema::create('concert_tracker_task_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('concert_tracker_task_id')->constrained(indexName: 'tracker_note_task_fk')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->date('follow_up_date')->nullable();
            $table->timestamps();
        });

        Schema::create('concert_tracker_task_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('concert_tracker_task_id')->constrained(indexName: 'tracker_change_task_fk')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 50);
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('concert_tracker_email_templates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('task_key', 80)->unique();
            $table->string('name');
            $table->string('subject');
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('concert_tracker_email_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('concert_tracker_task_id')->constrained(indexName: 'tracker_delivery_task_fk')->cascadeOnDelete();
            $table->foreignId('concert_tracker_email_template_id')->constrained('concert_tracker_email_templates', indexName: 'tracker_delivery_template_fk')->restrictOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_email');
            $table->string('subject');
            $table->text('body');
            $table->string('status', 24)->default('queued');
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamps();
            $table->index(['status', 'queued_at']);
        });

        $now = now();
        foreach ($this->templates() as $taskKey => $template) {
            DB::table('concert_tracker_email_templates')->insert([
                'uuid' => (string) Str::uuid(),
                'task_key' => $taskKey,
                'name' => $template['name'],
                'subject' => $template['subject'],
                'body' => $template['body'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('concert_tracker_email_deliveries');
        Schema::dropIfExists('concert_tracker_email_templates');
        Schema::dropIfExists('concert_tracker_task_changes');
        Schema::dropIfExists('concert_tracker_task_notes');
        Schema::dropIfExists('concert_tracker_tasks');
    }

    private function templates(): array
    {
        return [
            'confirmation_information_email' => [
                'name' => 'Booking confirmation',
                'subject' => '{{ event_name }} booking confirmation',
                'body' => "Hi {{ contact_name }},\n\nYour booking for {{ event_name }} on {{ event_date }} is confirmed. Please let us know if the venue, times or services change.\n\nKind regards,\nDancePro",
            ],
            'final_numbers_email' => [
                'name' => 'Final numbers and technical information',
                'subject' => 'Final information needed for {{ event_name }}',
                'body' => "Hi {{ contact_name }},\n\nPlease send the final family numbers and technical information for {{ event_name }}.\n\nKind regards,\nDancePro",
            ],
            'concert_week_email' => [
                'name' => 'Concert week requirements',
                'subject' => 'Concert week information – {{ event_name }}',
                'body' => "Hi {{ contact_name }},\n\n{{ event_name }} is coming up on {{ event_date }}. Please review the final event requirements and let us know about any changes.\n\nKind regards,\nDancePro",
            ],
            'post_concert_email' => [
                'name' => 'Post-concert information',
                'subject' => 'What happens next – {{ event_name }}',
                'body' => "Hi {{ contact_name }},\n\nThank you for having DancePro at {{ event_name }}. We are now preparing the event media and will contact you when approval is ready.\n\nKind regards,\nDancePro",
            ],
            'slideshow_request_email' => [
                'name' => 'Studio video or slideshow request',
                'subject' => 'Studio files needed for {{ event_name }}',
                'body' => "Hi {{ contact_name }},\n\nPlease send any studio video or slideshow files required for {{ event_name }}.\n\nKind regards,\nDancePro",
            ],
            'approval_link_email' => [
                'name' => 'Video approval link',
                'subject' => '{{ event_name }} is ready for review',
                'body' => "Hi {{ contact_name }},\n\n{{ event_name }} is ready for review. Use the approval link supplied with this message to check the concert and send any corrections.\n\nKind regards,\nDancePro",
            ],
            'invoice_sent' => [
                'name' => 'Final invoice',
                'subject' => 'Final invoice – {{ studio_name }}',
                'body' => "Hi {{ contact_name }},\n\nThe final invoice for your DancePro concert booking is ready. Payment is due seven days after the final concert.\n\nKind regards,\nDancePro",
            ],
        ];
    }
};
