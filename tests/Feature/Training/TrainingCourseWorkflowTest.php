<?php

namespace Tests\Feature\Training;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Models\CrewRole;
use App\Features\Training\Models\TrainingCourse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainingCourseWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_builds_a_mixed_course_and_crew_permanently_completes_it(): void
    {
        $admin = User::factory()->staff()->create();
        $crew = User::factory()->crew()->create();
        CrewProfile::factory()->for($crew)->create();

        $this->actingAs($admin)->post(route('admin.training-courses.store'), [
            'title' => 'New camera update',
            'description' => 'Watch the update and confirm the key setting.',
            'estimated_minutes' => 8,
            'status' => 'published',
            'is_required' => '1',
            'modules' => [
                ['title' => 'Watch the update', 'module_type' => 'video', 'video_url' => 'https://example.test/camera-video'],
                ['title' => 'Quick check', 'module_type' => 'quiz', 'quiz_question' => 'Which setting?', 'quiz_options_text' => "A\nB", 'correct_option' => 1],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $course = TrainingCourse::query()->firstOrFail()->load('modules');
        $this->actingAs($crew)->get(route('crew.training.index'))
            ->assertOk()->assertSee('New camera update')->assertSee('Required');
        $this->actingAs($crew)->get(route('crew.training.show', $course))
            ->assertOk()->assertSee('Watch video')->assertSee('Which setting?');

        $video = $course->modules[0];
        $quiz = $course->modules[1];
        $this->actingAs($crew)->post(route('crew.training.modules.complete', [$course, $video]))->assertRedirect();
        $this->actingAs($crew)->post(route('crew.training.modules.complete', [$course, $quiz]), ['selected_option' => 0])->assertRedirect();
        $this->assertDatabaseHas('training_enrolments', ['training_course_id' => $course->id, 'status' => 'in_progress']);
        $this->actingAs($crew)->post(route('crew.training.modules.complete', [$course, $quiz]), ['selected_option' => 1])->assertRedirect();
        $this->assertDatabaseHas('training_enrolments', ['training_course_id' => $course->id, 'status' => 'completed']);
        $this->actingAs($crew)->get(route('crew.profile.edit'))
            ->assertOk()->assertSee('New camera update')->assertSee('Course completed');

        $this->actingAs($admin)->put(route('admin.training-courses.update', $course), [
            'title' => 'Changed course', 'status' => 'published', 'modules' => [['title' => 'Replacement', 'module_type' => 'lesson']],
        ])->assertSessionHasErrors('course');
        $this->assertSame('New camera update', $course->refresh()->title);
    }

    public function test_admin_builds_ordered_sections_with_rich_content_blocks(): void
    {
        $admin = User::factory()->staff()->create();
        $crew = User::factory()->crew()->create();
        CrewProfile::factory()->for($crew)->create();

        $this->actingAs($admin)->get(route('admin.training-courses.create'))
            ->assertOk()->assertSee('Course content')->assertSee('Add section')->assertSee('Image gallery');

        $this->actingAs($admin)->post(route('admin.training-courses.store'), [
            'title' => 'Competition camera setup',
            'status' => 'published',
            'sections' => [
                ['title' => 'Before unpacking', 'description' => 'Prepare the work area.', 'blocks' => [
                    ['title' => 'Safety reminder', 'module_type' => 'callout', 'content' => 'Keep the aisle clear.'],
                ]],
                ['title' => 'Build the camera', 'blocks' => [
                    ['title' => 'Setup order', 'module_type' => 'process', 'items_text' => "Tripod\nCamera\nPower"],
                    ['title' => 'Reference sheet', 'module_type' => 'file', 'media_url' => 'https://example.test/setup.pdf', 'button_label' => 'Open setup sheet'],
                ]],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $course = TrainingCourse::query()->firstOrFail()->load('sections.modules');
        $this->assertSame(['Before unpacking', 'Build the camera'], $course->sections->pluck('title')->all());
        $this->assertSame(['Setup order', 'Reference sheet'], $course->sections[1]->modules->pluck('title')->all());
        $this->assertSame(['Tripod', 'Camera', 'Power'], $course->sections[1]->modules[0]->settings['items']);

        $this->actingAs($crew)->get(route('crew.training.show', $course))
            ->assertOk()->assertSeeInOrder(['Before unpacking', 'Safety reminder', 'Build the camera', 'Setup order', 'Reference sheet'])
            ->assertSee('Open setup sheet');
    }

    public function test_crew_completes_a_scored_assessment_with_feedback_and_attempt_limits(): void
    {
        $admin = User::factory()->staff()->create();
        $crew = User::factory()->crew()->create();
        CrewProfile::factory()->for($crew)->create();

        $this->actingAs($admin)->post(route('admin.training-courses.store'), [
            'title' => 'Camera safety assessment', 'status' => 'published',
            'sections' => [['title' => 'Assessment', 'blocks' => [[
                'title' => 'Final check', 'module_type' => 'assessment', 'pass_mark' => 100, 'max_attempts' => 2, 'show_feedback' => '1',
                'questions' => [
                    ['prompt' => 'Which battery?', 'type' => 'single_choice', 'options_text' => "Empty\nCharged", 'correct_answer' => 2, 'points' => 1, 'feedback' => 'Use a charged battery.'],
                    ['prompt' => 'Select the safety steps', 'type' => 'multiple_choice', 'options_text' => "Tape cables\nBlock aisle\nFit weights", 'correct_answers_text' => '1, 3', 'points' => 2],
                    ['prompt' => 'Type the call word', 'type' => 'short_answer', 'correct_answer_value' => 'clear', 'points' => 1],
                ],
            ]]]],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $course = TrainingCourse::query()->firstOrFail()->load('modules');
        $assessment = $course->modules->first();
        $this->actingAs($crew)->get(route('crew.training.show', $course))->assertOk()->assertSee('Pass mark: 100%')->assertSee('Which battery?');

        $this->actingAs($crew)->post(route('crew.training.modules.complete', [$course, $assessment]), [
            'answers' => [0, [0], 'wrong'],
        ])->assertRedirect()->assertSessionHas('status', 'Assessment score: 0%. Review the feedback and try again.');
        $this->assertDatabaseHas('training_assessment_attempts', ['training_module_id' => $assessment->id, 'attempt_number' => 1, 'passed' => false]);
        $this->actingAs($crew)->get(route('crew.training.show', $course))->assertOk()->assertSee('Use a charged battery.');

        $this->actingAs($crew)->post(route('crew.training.modules.complete', [$course, $assessment]), [
            'answers' => [1, [0, 2], 'CLEAR'],
        ])->assertRedirect()->assertSessionHas('status', 'Assessment passed with 100%.');
        $this->assertDatabaseHas('training_enrolments', ['training_course_id' => $course->id, 'status' => 'completed']);
        $this->assertDatabaseCount('training_assessment_attempts', 2);
    }

    public function test_admin_assigns_training_with_a_due_date_and_monitors_progress(): void
    {
        $admin = User::factory()->staff()->create();
        $crew = User::factory()->crew()->create(['name' => 'Alex Camera']);
        $profile = CrewProfile::factory()->for($crew)->create(['preferred_name' => 'Alex Camera']);
        $unassignedCrew = User::factory()->crew()->create();
        CrewProfile::factory()->for($unassignedCrew)->create();
        $unrelatedRole = CrewRole::query()->create(['name' => 'Specialist trainer', 'code' => 'specialist-trainer', 'is_active' => true]);
        $course = TrainingCourse::query()->create([
            'crew_role_id' => $unrelatedRole->id,
            'title' => 'Assigned camera induction',
            'status' => 'published',
            'is_required' => true,
            'grants_role_qualification' => true,
        ]);
        $module = $course->modules()->create(['title' => 'Introduction', 'module_type' => 'text', 'sort_order' => 0]);

        $this->actingAs($admin)->put(route('admin.training-courses.assignments.update', $course), [
            'crew_profile_ids' => [$profile->id],
            'due_at' => today()->addWeek()->format('Y-m-d'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('training_enrolments', [
            'training_course_id' => $course->id,
            'crew_profile_id' => $profile->id,
            'assigned_by_user_id' => $admin->id,
            'status' => 'assigned',
            'due_at' => today()->addWeek()->format('Y-m-d').' 00:00:00',
        ]);
        $this->actingAs($admin)->get(route('admin.training-courses.assignments', $course))
            ->assertOk()->assertSee('Alex Camera')->assertSee('Assigned');
        $this->actingAs($admin)->get(route('admin.training-courses.overview', ['status' => 'assigned']))
            ->assertOk()->assertSee('Training overview')->assertSee('Alex Camera')->assertSee('Assigned camera induction');
        $enrolment = $course->enrolments()->where('crew_profile_id', $profile->id)->firstOrFail();
        $this->actingAs($admin)->post(route('admin.training-reminders.store', $enrolment), [
            'method' => 'phone', 'note' => 'Confirmed the due date.',
        ])->assertRedirect()->assertSessionHas('status', 'Reminder recorded.');
        $this->assertDatabaseHas('training_reminders', ['training_enrolment_id' => $enrolment->id, 'method' => 'phone', 'note' => 'Confirmed the due date.']);
        $this->actingAs($crew)->get(route('crew.training.index'))
            ->assertOk()->assertSee('Assigned camera induction')->assertSee('Due '.today()->addWeek()->format('j M Y'));
        $this->actingAs($unassignedCrew)->get(route('crew.training.show', $course))->assertForbidden();

        $this->actingAs($crew)->get(route('crew.training.show', $course))->assertOk();
        $this->assertDatabaseHas('training_enrolments', ['training_course_id' => $course->id, 'crew_profile_id' => $profile->id, 'status' => 'in_progress']);
        $this->actingAs($crew)->post(route('crew.training.modules.complete', [$course, $module]))->assertRedirect();
        $this->assertDatabaseHas('training_enrolments', ['training_course_id' => $course->id, 'crew_profile_id' => $profile->id, 'status' => 'completed']);
        $this->assertDatabaseHas('crew_role_qualifications', [
            'crew_profile_id' => $profile->id,
            'crew_role_id' => $unrelatedRole->id,
            'status' => 'approved',
            'effective_until' => null,
        ]);
        $this->actingAs($admin)->get(route('admin.training-courses.crew-history', $profile))
            ->assertOk()->assertSee('Permanent course history')->assertSee('Confirmed the due date.')->assertSee('Completed');

        $export = $this->actingAs($admin)->get(route('admin.training-courses.export'));
        $export->assertOk()->assertDownload('dancepro-training-report-'.today()->format('Y-m-d').'.csv');
        $this->assertStringContainsString('Assigned camera induction', $export->streamedContent());

        $this->actingAs($admin)->put(route('admin.training-courses.assignments.update', $course), ['crew_profile_ids' => []])->assertRedirect();
        $this->assertDatabaseHas('training_enrolments', ['training_course_id' => $course->id, 'crew_profile_id' => $profile->id, 'status' => 'completed']);
    }

    public function test_role_qualification_award_requires_a_target_role(): void
    {
        $admin = User::factory()->staff()->create();

        $this->actingAs($admin)->post(route('admin.training-courses.store'), [
            'title' => 'General induction',
            'status' => 'draft',
            'grants_role_qualification' => '1',
            'modules' => [['title' => 'Welcome', 'module_type' => 'text']],
        ])->assertSessionHasErrors('grants_role_qualification');
    }
}
