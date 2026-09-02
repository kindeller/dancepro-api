<?php

namespace App\Features\Training\Services;

use App\Features\Training\Models\TrainingModule;

class EvaluateTrainingAssessment
{
    public function evaluate(TrainingModule $module, array $answers): array
    {
        $assessment = data_get($module->settings, 'assessment', []);
        $earned = 0;
        $available = 0;
        $results = [];

        foreach (data_get($assessment, 'questions', []) as $index => $question) {
            $points = max(1, (int) ($question['points'] ?? 1));
            $available += $points;
            $answer = $answers[$index] ?? null;
            $correct = $this->isCorrect($question, $answer);
            $earned += $correct ? $points : 0;
            $results[] = ['correct' => $correct, 'points_earned' => $correct ? $points : 0, 'points_available' => $points, 'feedback' => $question['feedback'] ?? null];
        }

        $score = $available > 0 ? round(($earned / $available) * 100, 2) : 0;

        return ['score_percent' => $score, 'passed' => $score >= (int) data_get($assessment, 'pass_mark', 80), 'results' => $results];
    }

    private function isCorrect(array $question, mixed $answer): bool
    {
        return match ($question['type'] ?? 'single_choice') {
            'multiple_choice' => $this->normaliseArray($answer) === $this->normaliseArray($question['correct_answers'] ?? []),
            'short_answer' => mb_strtolower(trim((string) $answer)) === mb_strtolower(trim((string) ($question['correct_answer'] ?? ''))),
            'number' => is_numeric($answer) && is_numeric($question['correct_answer'] ?? null) && (float) $answer === (float) $question['correct_answer'],
            'ordering' => array_values((array) $answer) === array_values($question['options'] ?? []),
            default => (string) $answer === (string) ($question['correct_answer'] ?? ''),
        };
    }

    private function normaliseArray(mixed $value): array
    {
        $answers = array_map('strval', is_array($value) ? $value : [$value]);
        sort($answers);

        return $answers;
    }
}
