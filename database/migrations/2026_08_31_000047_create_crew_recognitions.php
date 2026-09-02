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
        Schema::create('recognition_types', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('icon', 20);
            $table->string('design');
            $table->text('default_message')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('crew_recognitions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('recognition_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('crew_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scheduling_event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('awarded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('message');
            $table->string('icon', 20);
            $table->string('design');
            $table->date('awarded_on');
            $table->boolean('show_on_profile')->default(true);
            $table->timestamps();
        });

        $now = now();
        $examples = [
            ['Above & Beyond', '🌟', 'gold-star', 'For stepping beyond the brief and making the event better for everyone.'],
            ['Calm Under Pressure', '🧊', 'teal-compass', 'For staying composed, practical and reassuring when the pressure was on.'],
            ['Client Praise', '💬', 'purple-heart', 'For receiving exceptional positive feedback from a studio, organiser or venue.'],
            ['Crew Champion', '🤝', 'dp-blue', 'For actively supporting the crew and helping the whole team succeed.'],
            ['Problem Solver', '🧩', 'green-shield', 'For finding a smart, effective solution to an unexpected problem.'],
            ['Leadership', '👑', 'midnight-crown', 'For clear direction, good judgement and looking after the team.'],
            ['Technical Excellence', '🎯', 'red-bolt', 'For exceptional technical accuracy, consistency and attention to detail.'],
            ['Team Spirit', '💙', 'dp-blue', 'For bringing generosity, positivity and excellent energy to the crew.'],
            ['Outstanding Setup', '⚡', 'red-bolt', 'For an efficient, safe and exceptionally well-organised event setup.'],
            ['Mentor', '🧭', 'teal-compass', 'For patiently sharing knowledge and helping another crew member grow.'],
            ['Safety First', '🛡️', 'green-shield', 'For proactively protecting the crew, performers and public.'],
            ['Event Hero', '🎬', 'gold-star', 'For a standout contribution that made a difficult event successful.'],
        ];
        DB::table('recognition_types')->insert(array_map(fn (array $example): array => [
            'uuid' => (string) Str::uuid(),
            'name' => $example[0],
            'icon' => $example[1],
            'design' => $example[2],
            'default_message' => $example[3],
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], $examples));
    }

    public function down(): void
    {
        Schema::dropIfExists('crew_recognitions');
        Schema::dropIfExists('recognition_types');
    }
};
