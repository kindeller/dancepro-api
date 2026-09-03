<?php

namespace App\Features\Training\Actions;

use App\Features\Training\Models\TrainingAssessmentAttempt;
use App\Features\Training\Models\TrainingEnrolment;
use App\Features\Training\Models\TrainingModule;
use App\Features\Training\Services\EvaluateTrainingAssessment;
use Illuminate\Support\Facades\DB;

class CompleteTrainingModule
{
    public function __construct(
        private readonly EvaluateTrainingAssessment $evaluateAssessment,
        private readonly AwardTrainingRoleQualification $awardRoleQualification,
    ) {}

    public function execute(TrainingEnrolment $enrolment, TrainingModule $module, array $data): string
    {
        return DB::transaction(function () use ($enrolment, $module, $data): string {
            $progress = $enrolment->moduleProgress()->lockForUpdate()->firstOrNew(['training_module_id' => $module->id]);

            if ($progress->completed_at !== null) {
                return 'Module already completed.';
            }

            $nextAttempt = $progress->attempts + 1;
            $selected = $data['selected_option'] ?? null;
            $evaluation = null;

            if ($module->module_type === 'assessment') {
                $maxAttempts = data_get($module->settings, 'assessment.max_attempts');
                abort_if($maxAttempts && $progress->attempts >= $maxAttempts, 422, 'No assessment attempts remain.');
                $answers = $data['answers'] ?? [];
                $evaluation = $this->evaluateAssessment->evaluate($module, $answers);
                TrainingAssessmentAttempt::query()->create([
                    'training_enrolment_id' => $enrolment->id,
                    'training_module_id' => $module->id,
                    'attempt_number' => $nextAttempt,
                    'score_percent' => $evaluation['score_percent'],
                    'passed' => $evaluation['passed'],
                    'answers' => $answers,
                    'results' => $evaluation['results'],
                    'submitted_at' => now(),
                ]);
                $passed = $evaluation['passed'];
            } else {
                $passed = $module->module_type !== 'quiz' || $selected === $module->correct_option;
            }

            $progress->fill(['selected_option' => $selected, 'passed' => $passed, 'attempts' => $nextAttempt, 'completed_at' => $passed ? now() : null])->save();
            if ($enrolment->moduleProgress()->whereNotNull('completed_at')->count() === $module->course->modules()->count()) {
                $enrolment->update(['status' => 'completed', 'completed_at' => now()]);
                $this->awardRoleQualification->execute($enrolment->loadMissing('course'));
            }

            if ($evaluation) {
                return $passed ? "Assessment passed with {$evaluation['score_percent']}%." : "Assessment score: {$evaluation['score_percent']}%. Review the feedback and try again.";
            }

            return $passed ? 'Module completed.' : 'Not quite. Review the material and try again.';
        });
    }
}
