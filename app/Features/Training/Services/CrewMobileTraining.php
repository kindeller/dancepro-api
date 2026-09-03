<?php

namespace App\Features\Training\Services;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Training\Models\TrainingAssessmentAttempt;
use App\Features\Training\Models\TrainingCourse;
use App\Features\Training\Models\TrainingEnrolment;
use App\Features\Training\Models\TrainingModule;

class CrewMobileTraining
{
    public function __construct(private readonly AvailableTrainingCourses $available) {}

    public function course(CrewProfile $profile, TrainingCourse $course): TrainingCourse
    {
        return $this->available->for($profile)->firstWhere('id', $course->id) ?? abort(404);
    }

    public function enrolment(CrewProfile $profile, TrainingCourse $course): TrainingEnrolment
    {
        $enrolment = TrainingEnrolment::query()->firstOrCreate(
            ['training_course_id' => $course->id, 'crew_profile_id' => $profile->id],
            ['status' => 'in_progress', 'started_at' => now()],
        );

        if ($enrolment->status === 'assigned') {
            $enrolment->update(['status' => 'in_progress', 'started_at' => $enrolment->started_at ?? now()]);
        }

        return $enrolment->load('moduleProgress');
    }

    public function detail(TrainingCourse $course, TrainingEnrolment $enrolment): array
    {
        $course->loadMissing('sections.modules');
        $attempts = TrainingAssessmentAttempt::query()
            ->where('training_enrolment_id', $enrolment->id)
            ->orderByDesc('attempt_number')->get()->unique('training_module_id')->keyBy('training_module_id');

        return [
            'id' => $course->uuid,
            'title' => $course->title,
            'description' => $course->description,
            'estimated_minutes' => $course->estimated_minutes,
            'required' => $course->is_required,
            'status' => $enrolment->status,
            'due_at' => $enrolment->due_at?->startOfDay()->toIso8601String(),
            'completed_at' => $enrolment->completed_at?->toIso8601String(),
            'sections' => $course->sections->map(fn ($section): array => [
                'title' => $section->title,
                'description' => $section->description,
                'modules' => $section->modules->map(fn (TrainingModule $module): array => $this->module($module, $enrolment, $attempts->get($module->id)))->values(),
            ])->values(),
        ];
    }

    private function module(TrainingModule $module, TrainingEnrolment $enrolment, ?TrainingAssessmentAttempt $attempt): array
    {
        $progress = $enrolment->moduleProgress->firstWhere('training_module_id', $module->id);
        $settings = $module->settings ?? [];
        $assessment = data_get($settings, 'assessment', []);
        $showFeedback = (bool) ($assessment['show_feedback'] ?? false);

        return [
            'id' => $module->uuid,
            'title' => $module->title,
            'type' => $module->module_type,
            'content' => $module->content,
            'media_url' => data_get($settings, 'media_url', $module->video_url),
            'button_label' => $settings['button_label'] ?? null,
            'confirmation_text' => $settings['confirmation_text'] ?? null,
            'items' => $settings['items'] ?? [],
            'quiz' => $module->module_type === 'quiz' ? [
                'question' => $module->quiz_question,
                'options' => $module->quiz_options ?? [],
            ] : null,
            'assessment' => $module->module_type === 'assessment' ? [
                'pass_mark' => (int) ($assessment['pass_mark'] ?? 80),
                'max_attempts' => isset($assessment['max_attempts']) ? (int) $assessment['max_attempts'] : null,
                'questions' => collect($assessment['questions'] ?? [])->map(fn (array $question): array => [
                    'prompt' => $question['prompt'],
                    'type' => $question['type'],
                    'options' => $question['options'] ?? [],
                    'points' => (int) ($question['points'] ?? 1),
                ])->values(),
            ] : null,
            'progress' => [
                'completed' => $progress?->completed_at !== null,
                'passed' => $progress?->passed,
                'attempts' => $progress?->attempts ?? 0,
                'completed_at' => $progress?->completed_at?->toIso8601String(),
                'latest_assessment' => $attempt ? [
                    'score_percent' => (float) $attempt->score_percent,
                    'passed' => $attempt->passed,
                    'results' => $showFeedback ? $attempt->results : null,
                    'submitted_at' => $attempt->submitted_at->toIso8601String(),
                ] : null,
            ],
        ];
    }
}
