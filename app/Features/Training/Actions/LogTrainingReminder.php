<?php

namespace App\Features\Training\Actions;

use App\Features\Training\Models\TrainingEnrolment;
use App\Features\Training\Models\TrainingReminder;

class LogTrainingReminder
{
    public function execute(TrainingEnrolment $enrolment, array $data, int $userId): TrainingReminder
    {
        return $enrolment->reminders()->create([
            'recorded_by_user_id' => $userId,
            'method' => $data['method'],
            'note' => $data['note'] ?? null,
            'reminded_at' => now(),
        ]);
    }
}
