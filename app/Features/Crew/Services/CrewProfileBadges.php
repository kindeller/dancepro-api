<?php

namespace App\Features\Crew\Services;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Models\CrewRoleQualification;
use App\Features\Crew\Support\CrewRoleQualificationStatus;
use App\Features\Scheduling\Support\SchedulingEventType;
use Illuminate\Support\Collection;

class CrewProfileBadges
{
    public function for(CrewProfile $profile): Collection
    {
        $profile->loadMissing(['roleQualifications.crewRole', 'shiftAssignments.shift.schedulingEvent', 'trainingEnrolments.course', 'recognitions.schedulingEvent']);

        return collect([
            'training' => $this->trainingBadges($profile),
            'milestones' => $this->milestoneBadges($profile),
            'recognition' => $this->recognitionBadges($profile),
            'rewards' => collect(),
        ])->filter(fn (Collection $badges): bool => $badges->isNotEmpty());
    }

    private function recognitionBadges(CrewProfile $profile): Collection
    {
        return $profile->recognitions
            ->where('show_on_profile', true)
            ->sortByDesc('awarded_on')
            ->map(fn ($recognition): array => [
                'icon' => $recognition->icon,
                'name' => $recognition->title,
                'detail' => collect([$recognition->message, $recognition->awarded_on->format('d M Y'), $recognition->schedulingEvent?->name])->filter()->join(' · '),
                'design' => $recognition->design,
                'url' => null,
            ])->values();
    }

    private function trainingBadges(CrewProfile $profile): Collection
    {
        $roleBadges = $profile->roleQualifications
            ->filter(fn (CrewRoleQualification $qualification): bool => $qualification->status === CrewRoleQualificationStatus::Approved)
            ->filter(fn (CrewRoleQualification $qualification): bool => $qualification->effective_until === null || $qualification->effective_until->isFuture())
            ->map(fn (CrewRoleQualification $qualification): array => [
                'icon' => $this->trainingIcon($qualification->crewRole->name),
                'name' => $qualification->crewRole->name,
                'detail' => 'Qualified'.($qualification->effective_from ? ' · '.$qualification->effective_from->format('Y') : ''),
                'url' => null,
            ])
            ->sortBy('name')
            ->values();

        $courseBadges = $profile->trainingEnrolments
            ->where('status', 'completed')
            ->map(fn ($enrolment): array => [
                'icon' => $this->trainingIcon($enrolment->course->title),
                'name' => $enrolment->course->title,
                'detail' => 'Course completed · '.$enrolment->completed_at->format('Y'),
                'url' => route('crew.training.show', $enrolment->course),
            ]);

        return $roleBadges->concat($courseBadges)->sortBy('name')->values();
    }

    private function milestoneBadges(CrewProfile $profile): Collection
    {
        $badges = collect();
        $years = $profile->completedYearsOfService();

        foreach ([1, 3, 5, 10, 15, 20] as $milestone) {
            if ($years !== null && $years >= $milestone) {
                $badges->push([
                    'icon' => $milestone,
                    'name' => $milestone.' '.str('year')->plural($milestone).' with DancePro',
                    'detail' => 'Service milestone',
                    'url' => null,
                ]);
            }
        }

        $concertCount = $profile->shiftAssignments
            ->filter(fn ($assignment): bool => $assignment->status === 'published'
                && $assignment->shift?->shift_date?->isPast()
                && $assignment->shift?->schedulingEvent?->event_type === SchedulingEventType::Concert)
            ->pluck('shift.scheduling_event_id')
            ->unique()
            ->count();

        foreach ([25, 50, 100, 250] as $milestone) {
            if ($concertCount >= $milestone) {
                $badges->push([
                    'icon' => $milestone,
                    'name' => $milestone.' concerts',
                    'detail' => 'Event milestone',
                    'url' => null,
                ]);
            }
        }

        return $badges;
    }

    private function trainingIcon(string $roleName): string
    {
        $roleName = str($roleName)->lower();

        return match (true) {
            $roleName->contains('video') => '🎥',
            $roleName->contains('photo') => '📷',
            $roleName->contains(['team leader', 'leader']) => '👑',
            $roleName->contains(['runner', 'assistant']) => '🏃',
            $roleName->contains(['edit', 'media']) => '💻',
            default => '✓',
        };
    }
}
