<?php

namespace App\Features\Tracking\Services;

use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Tracking\Actions\UpdateConcertTrackerTask;
use App\Features\Tracking\Models\ConcertTrackerAutomationRule;
use App\Features\Tracking\Models\ConcertTrackerEmailDelivery;
use App\Features\Tracking\Models\ConcertTrackerEmailTemplate;
use App\Features\Tracking\Models\ConcertTrackerSetting;
use App\Features\Tracking\Models\StaffEmailSignature;
use App\Features\Tracking\Support\ConcertTrackerTaskCatalog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ConcertTrackerAutomation
{
    private const RULES = [
        'confirmation_information_email' => ['label' => 'Booking confirmation', 'basis' => 'approved'],
        'final_numbers_email' => ['label' => 'Seats & families email', 'basis' => 'booking_first'],
        'concert_week_email' => ['label' => 'Concert Week requirements', 'basis' => 'concert_group'],
        'post_concert_email' => ['label' => 'Post-concert information', 'basis' => 'concert_group_last'],
    ];

    public function __construct(
        private readonly ConcertTrackerTaskCatalog $catalog,
        private readonly UpdateConcertTrackerTask $tasks,
        private readonly ConcertTrackerEmails $emails,
    ) {}

    public function page(int $year): array
    {
        $candidates = $this->candidatesForYear($year);

        return compact('year', 'candidates');
    }

    public function candidatesForYear(int $year): Collection
    {
        $events = SchedulingEvent::query()->where('event_type', 'concert')->whereNull('cancelled_at')
            ->whereYear('event_date', $year)->with(['studio', 'concertBookingItem.booking.items'])->orderBy('event_date')->get();
        $candidates = $events->map(fn (SchedulingEvent $event): Collection => $this->forEvent($event))->flatten(1)
            ->unique(fn (array $candidate): string => $candidate['task']->id.':'.$candidate['template']->id)
            ->sortBy('planned_at')->values();

        return $candidates;
    }

    public function settingsPage(User $actor): array
    {
        return [
            'rules' => ConcertTrackerAutomationRule::query()->get()->keyBy('task_key'),
            'definitions' => self::RULES,
            'settings' => ConcertTrackerSetting::query()->firstOrCreate([], ['escalation_after_days' => 2, 'editing_target_days' => 14, 'approval_target_days' => 14]),
            'staff' => User::query()->where('is_admin', true)->where('is_active', true)->orderBy('name')->get(),
            'signature' => StaffEmailSignature::firstOrNew(['user_id' => $actor->id], ['display_name' => $actor->name, 'is_enabled' => true]),
        ];
    }

    public function updateSettings(array $input, User $actor): void
    {
        foreach ($input['rules'] as $taskKey => $rule) {
            if (isset(self::RULES[$taskKey])) {
                ConcertTrackerAutomationRule::where('task_key', $taskKey)->update($rule);
            }
        }
        (ConcertTrackerSetting::query()->first() ?? ConcertTrackerSetting::create())->update(collect($input)->except(['rules', 'signature_display_name', 'signature_position', 'signature_phone', 'signature_sign_off', 'signature_is_enabled'])->all());
        StaffEmailSignature::updateOrCreate(['user_id' => $actor->id], [
            'display_name' => $input['signature_display_name'],
            'position' => $input['signature_position'] ?? null,
            'phone' => $input['signature_phone'] ?? null,
            'sign_off' => $input['signature_sign_off'] ?? null,
            'is_enabled' => (bool) ($input['signature_is_enabled'] ?? false),
        ]);
    }

    public function scheduleAutomatic(): int
    {
        $count = 0;
        foreach ([now()->year, now()->year + 1] as $year) {
            foreach ($this->candidatesForYear($year)->where('mode', 'automatic')->whereNull('existing')->filter(fn (array $candidate): bool => filled($candidate['recipient_email'])) as $candidate) {
                $plannedAt = $candidate['planned_at']->isFuture() ? $candidate['planned_at'] : now()->addMinutes(5);
                $this->emails->queue($candidate['event'], $candidate['task'], $candidate['template'], null, [
                    'recipient_name' => $candidate['recipient_name'], 'recipient_email' => $candidate['recipient_email'],
                    'scheduled_at' => $plannedAt->toDateTimeString(),
                ]);
                $count++;
            }
        }

        return $count;
    }

    public function schedule(SchedulingEvent $event, string $taskKey, User $actor): ConcertTrackerEmailDelivery
    {
        $candidate = $this->forEvent($event)->firstWhere('task_key', $taskKey);
        if ($event->cancelled_at || ! $candidate || ($candidate['existing'] && ! $candidate['needs_review'])) {
            throw ValidationException::withMessages(['email' => 'This email is already scheduled or has been sent.']);
        }
        if (blank($candidate['recipient_email'])) {
            throw ValidationException::withMessages(['email' => 'Add a studio contact email before scheduling this message.']);
        }
        $plannedAt = $candidate['planned_at']->isFuture() ? $candidate['planned_at'] : now()->addMinutes(5);

        return $this->emails->queue($event, $candidate['task'], $candidate['template'], $actor, [
            'recipient_name' => $candidate['recipient_name'],
            'recipient_email' => $candidate['recipient_email'],
            'scheduled_at' => $plannedAt->toDateTimeString(),
        ]);
    }

    private function forEvent(SchedulingEvent $event): Collection
    {
        if ($event->concertBookingItem?->cancelled_at) {
            return collect();
        }
        $storedRules = ConcertTrackerAutomationRule::query()->get()->keyBy('task_key');

        return collect(self::RULES)->map(function (array $definitionRule, string $taskKey) use ($event, $storedRules): ?array {
            $rule = $storedRules->get($taskKey);
            if (! $rule || $rule->mode === 'disabled') {
                return null;
            }
            $definition = $this->catalog->find($taskKey);
            $template = ConcertTrackerEmailTemplate::where('task_key', $definition['templateKey'])->where('is_active', true)->first();
            if (! $template) {
                return null;
            }
            $task = $this->tasks->task($event, $definition);
            $booking = $event->concertBookingItem?->booking;
            $plannedAt = $this->plannedAt($event, $definitionRule, $rule);
            $existing = ConcertTrackerEmailDelivery::query()->where('concert_tracker_task_id', $task->id)
                ->where('concert_tracker_email_template_id', $template->id)
                ->where(fn ($query) => $query->where('status', '!=', 'cancelled')->orWhereNotNull('invalidated_at'))
                ->latest('id')->first();

            return [
                'event' => $event, 'task' => $task, 'task_key' => $taskKey, 'template' => $template,
                'label' => $definitionRule['label'], 'planned_at' => $plannedAt, 'existing' => $existing,
                'mode' => $rule->mode, 'needs_review' => $existing?->invalidated_at !== null,
                'recipient_name' => $booking?->contact_name ?? $event->organiser_name ?? $event->studio?->contact_name,
                'recipient_email' => $booking?->contact_email ?? $event->organiser_email ?? $event->studio?->contact_email,
            ];
        })->filter()->values();
    }

    private function plannedAt(SchedulingEvent $event, array $definition, ConcertTrackerAutomationRule $rule): Carbon
    {
        if ($definition['basis'] === 'concert_group') {
            $date = Carbon::parse(app(ConcertEventGroups::class)->firstEventDate($event));
        } elseif ($definition['basis'] === 'concert_group_last') {
            $date = Carbon::parse(app(ConcertEventGroups::class)->lastEventDate($event));
        } elseif ($definition['basis'] === 'approved') {
            $approved = $event->concertBookingItem?->booking?->reviewed_at;
            $date = $approved ? Carbon::parse($approved) : now();
        } else {
            $date = $definition['basis'] === 'booking_first'
                ? Carbon::parse($event->concertBookingItem?->booking?->items->whereNull('cancelled_at')->pluck('event_date')->filter()->sort()->first())
                : Carbon::parse($event->event_date);
        }
        [$hour, $minute] = array_map('intval', explode(':', $rule->send_time));

        return $date->startOfDay()->setTime($hour, $minute)->addDays($rule->offset_days);
    }
}
