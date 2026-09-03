<?php

namespace Tests\Feature\Training;

use App\Features\Auth\Support\TokenAbility;
use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Models\CrewRole;
use App\Features\Training\Models\TrainingCourse;
use App\Features\Training\Models\TrainingEnrolment;
use App\Features\Training\Models\TrainingModule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrewMobileTrainingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_crew_can_read_available_course_content_without_answer_keys(): void
    {
        [, $profile] = $this->authenticatedCrew();
        [$course, $module] = $this->assignedAssessment($profile);

        $response = $this->getJson('/api/v1/training/'.$course->uuid)
            ->assertOk()
            ->assertJsonPath('data.id', $course->uuid)
            ->assertJsonPath('data.sections.0.modules.0.id', $module->uuid)
            ->assertJsonPath('data.sections.0.modules.0.assessment.questions.0.prompt', 'What is the call word?')
            ->assertJsonPath('data.sections.0.modules.0.progress.attempts', 0);

        $this->assertStringNotContainsString('SECRET-CALL', $response->getContent());
        $this->assertStringNotContainsString('correct_answer', $response->getContent());
        $this->assertStringNotContainsString('Use the safety call.', $response->getContent());
    }

    public function test_crew_can_complete_modules_and_receive_permitted_assessment_feedback(): void
    {
        [, $profile] = $this->authenticatedCrew();
        [$course, $module] = $this->assignedAssessment($profile);

        $failedAttemptKey = (string) Str::uuid();
        $failedResponse = $this->withHeader('Idempotency-Key', $failedAttemptKey)
            ->postJson('/api/v1/training/'.$course->uuid.'/modules/'.$module->uuid.'/complete', [
                'answers' => ['wrong'],
            ])->assertOk()
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.sections.0.modules.0.progress.attempts', 1)
            ->assertJsonPath('data.sections.0.modules.0.progress.latest_assessment.results.0.feedback', 'Use the safety call.');
        $this->withHeader('Idempotency-Key', $failedAttemptKey)
            ->postJson('/api/v1/training/'.$course->uuid.'/modules/'.$module->uuid.'/complete', [
                'answers' => ['wrong'],
            ])->assertOk()->assertHeader('Idempotency-Replayed', 'true')->assertExactJson($failedResponse->json());
        $this->assertDatabaseCount('training_assessment_attempts', 1);

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/training/'.$course->uuid.'/modules/'.$module->uuid.'/complete', [
                'answers' => ['secret-call'],
            ])->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.sections.0.modules.0.progress.completed', true);

        $this->assertDatabaseHas('training_enrolments', [
            'training_course_id' => $course->id,
            'crew_profile_id' => $profile->id,
            'status' => 'completed',
        ]);

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/training/'.$course->uuid.'/modules/'.$module->uuid.'/complete', [
                'answers' => ['secret-call'],
            ])->assertOk()->assertJsonPath('message', 'Module already completed.');
        $this->assertDatabaseCount('training_assessment_attempts', 2);
    }

    public function test_course_and_module_access_are_scoped_to_the_current_crew_member(): void
    {
        [, $profile] = $this->authenticatedCrew();
        [$course] = $this->assignedAssessment($profile);
        $otherCourse = TrainingCourse::query()->create(['title' => 'Other course', 'status' => 'published']);
        $otherSection = $otherCourse->sections()->create(['title' => 'Other']);
        $otherModule = $otherSection->modules()->create([
            'training_course_id' => $otherCourse->id, 'title' => 'Other module', 'module_type' => 'text',
        ]);

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/training/'.$course->uuid.'/modules/'.$otherModule->uuid.'/complete')
            ->assertNotFound();

        [$outsider] = $this->authenticatedCrew();
        $this->getJson('/api/v1/training/'.$course->uuid)->assertNotFound();
        $this->assertNotSame($profile->user_id, $outsider->id);
    }

    /** @return array{User, CrewProfile} */
    private function authenticatedCrew(): array
    {
        $user = User::factory()->crew()->create(['onboarding_completed_at' => now()]);
        $profile = CrewProfile::factory()->for($user)->create([
            'phone' => '0400 000 000', 'address_line_1' => '1 Test Street', 'suburb' => 'Perth',
            'state' => 'WA', 'postcode' => '6000', 'working_with_children_number' => 'WWC123',
            'working_with_children_expiry' => today()->addYear(),
        ]);
        Sanctum::actingAs($user, [TokenAbility::CrewMobile->value]);

        return [$user, $profile];
    }

    /** @return array{TrainingCourse, TrainingModule} */
    private function assignedAssessment(CrewProfile $profile): array
    {
        $role = CrewRole::factory()->create();
        $course = TrainingCourse::query()->create([
            'crew_role_id' => $role->id, 'title' => 'Camera assessment',
            'description' => 'Complete the camera safety assessment.', 'status' => 'published',
            'is_required' => true, 'estimated_minutes' => 15,
        ]);
        $section = $course->sections()->create(['title' => 'Safety', 'description' => 'Core safety']);
        $module = $section->modules()->create([
            'training_course_id' => $course->id,
            'title' => 'Safety check', 'module_type' => 'assessment',
            'settings' => ['assessment' => [
                'pass_mark' => 100, 'max_attempts' => 3, 'show_feedback' => true,
                'questions' => [[
                    'prompt' => 'What is the call word?', 'type' => 'short_answer',
                    'options' => [], 'correct_answer' => 'SECRET-CALL', 'points' => 1,
                    'feedback' => 'Use the safety call.',
                ]],
            ]],
        ]);
        TrainingEnrolment::query()->create([
            'training_course_id' => $course->id, 'crew_profile_id' => $profile->id,
            'status' => 'assigned', 'assigned_at' => now(),
        ]);

        return [$course, $module];
    }
}
