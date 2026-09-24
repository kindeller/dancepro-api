<?php

namespace App\Features\Tracking\Actions;

use App\Features\Tracking\Models\ConcertTrackerAlert;
use App\Features\Tracking\Models\ConcertTrackerSetting;
use App\Features\Tracking\Services\ConcertTracker;
use App\Features\Tracking\Services\ConcertTrackerAutomation;
use App\Models\User;

class CreateConcertTrackerAlerts
{
    public function __construct(
        private readonly ConcertTracker $tracker,
        private readonly ConcertTrackerAutomation $automation,
    ) {}

    public function execute(): int
    {
        ConcertTrackerAlert::query()->whereNull('resolved_at')->update(['resolved_at' => now()]);
        $admins = User::query()->where('is_admin', true)->where('is_active', true)->get();
        $settings = ConcertTrackerSetting::query()->first();
        $unassignedRecipients = $settings?->unassigned_alert_user_id
            ? $admins->where('id', $settings->unassigned_alert_user_id)
            : $admins;
        $active = 0;

        foreach ([now()->year - 1, now()->year, now()->year + 1] as $year) {
            $page = $this->tracker->page('date', $year, 'all');
            foreach ($page['rows'] as $row) {
                foreach ($row['tasks'] as $status) {
                    $type = $this->attentionType($status);
                    if (! $type) {
                        continue;
                    }
                    $users = $status['assignee'] ? collect([$status['assignee']]) : $unassignedRecipients;
                    foreach ($users as $user) {
                        $alert = $this->record($user, $row['event'], $status['override'], $type, $status['definition']['label'], $status['detail'], $status['detailUrl']);
                        $active++;
                        if ($status['assignee'] && $alert->created_at->lte(now()->subDays($settings?->escalation_after_days ?? 2))) {
                            foreach ($unassignedRecipients->where('id', '!=', $user->id) as $admin) {
                                $this->record($admin, $row['event'], $status['override'], 'escalated_'.$type, 'Escalated: '.$status['definition']['label'], $status['detail'], $status['detailUrl']);
                                $active++;
                            }
                        }
                    }
                }
            }
            foreach ($this->automation->candidatesForYear($year)->whereNull('recipient_email')->whereNull('existing') as $candidate) {
                foreach ($admins as $admin) {
                    $this->record($admin, $candidate['event'], $candidate['task'], 'missing_recipient', 'Missing recipient: '.$candidate['label'], 'Add a studio contact email before this message can be scheduled.', route('admin.concert-tracker.automation.index', ['year' => $year]));
                    $active++;
                }
            }
        }

        return $active;
    }

    private function attentionType(array $status): ?string
    {
        if ($status['key'] === 'failed') {
            return 'email_failed';
        }
        if ($status['override']?->follow_up_date?->lte(today()) && ! in_array($status['key'], ['complete', 'not_required'], true)) {
            return 'follow_up_due';
        }

        return $status['overdue'] ? 'task_overdue' : null;
    }

    private function record(User $user, mixed $event, mixed $task, string $type, string $title, string $body, string $url): ConcertTrackerAlert
    {
        $dedupe = $type.':'.$event->id.':'.($task?->id ?? sha1($title));
        $alert = ConcertTrackerAlert::firstOrNew(['user_id' => $user->id, 'dedupe_key' => $dedupe]);
        $reopened = $alert->exists && $alert->resolved_at !== null;
        $alert->fill([
            'scheduling_event_id' => $event->id,
            'concert_tracker_task_id' => $task?->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'resolved_at' => null,
            'read_at' => $reopened ? null : $alert->read_at,
        ])->save();

        return $alert;
    }
}
