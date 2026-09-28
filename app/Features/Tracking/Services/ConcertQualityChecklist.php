<?php

namespace App\Features\Tracking\Services;

use App\Features\Tracking\Actions\SaveConcertTrackerTaskFields;
use App\Features\Tracking\Models\ConcertTrackerTask;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConcertQualityChecklist
{
    public function groups(): array
    {
        return [
            'Files' => [
                'file_names' => 'Filenames are spelled correctly.',
                'all_items' => 'All items are included — no missed items.',
                'full_playback' => 'Items play in full, from start to end.',
            ],
            'Titles' => [
                'title_spelling' => 'Titles are spelled correctly.',
                'subtitle_spelling' => 'Subtitles are spelled correctly.',
                'title_timing' => 'All titles start at the right time.',
                'title_position' => 'All titles are in the correct place on the screen.',
                'font_size' => 'Font size is correct.',
            ],
            'Video' => [
                'fade_in' => 'Video fades in over 1 second.',
                'fade_out' => 'Video fades out over 2 seconds.',
                'widescreen' => 'All videos start in widescreen.',
                'dancers_routines' => 'No dancers or parts of routines are missed.',
                'video_quality' => 'Video is good quality.',
                'video_glitches' => 'There are no video glitches.',
                'straight_cameras' => 'Cameras are straight.',
                'brightness' => 'Brightness is acceptable.',
                'colour_balance' => 'Colour balance is acceptable.',
            ],
            'Audio & Music' => [
                'audio_quality' => 'Audio/music is good quality — no glitches, echo, hissing, crackling or rumbling.',
                'audience' => 'Clapping/audience can be heard at the end of the item.',
                'volume' => 'Volume is consistent throughout all items.',
            ],
        ];
    }

    public function keys(): array
    {
        return array_keys(array_merge(...array_values($this->groups())));
    }

    public function summary(?ConcertTrackerTask $task): array
    {
        $count = count(array_intersect($this->keys(), array_keys($task?->quality_checks ?? [])));

        return ['checked' => $count, 'total' => count($this->keys()), 'complete' => $count === count($this->keys())];
    }

    public function fingerprint(?ConcertTrackerTask $task): string
    {
        $checks = $task?->quality_checks ?? [];
        ksort($checks);
        foreach ($checks as &$check) {
            ksort($check);
        }
        unset($check);

        return hash('sha256', json_encode($checks, JSON_THROW_ON_ERROR));
    }

    public function page(ConcertTrackerTask $task): array
    {
        return $this->summary($task) + [
            'groups' => $this->groups(), 'checks' => $task->quality_checks ?? [], 'fingerprint' => $this->fingerprint($task),
            'reviewers' => User::withTrashed()->whereIn('id', collect($task->quality_checks ?? [])->pluck('user_id'))->pluck('name', 'id'),
        ];
    }

    public function save(ConcertTrackerTask $task, User $actor, array $keys, string $fingerprint): void
    {
        DB::transaction(function () use ($task, $actor, $keys, $fingerprint): void {
            $task = ConcertTrackerTask::whereKey($task->id)->lockForUpdate()->firstOrFail();
            if (! hash_equals($this->fingerprint($task), $fingerprint)) {
                throw ValidationException::withMessages(['checks' => 'This checklist changed while you were reviewing it. Reload the page before saving.']);
            }
            $before = $task->quality_checks ?? [];
            if (! $actor->canAccessAdmin() && count(array_diff($keys, array_keys($before))) + count(array_diff(array_keys($before), $keys)) > 1) {
                throw ValidationException::withMessages(['checks' => 'Staff must confirm quality checks individually.']);
            }
            $checks = [];
            foreach (array_intersect($this->keys(), $keys) as $key) {
                $checks[$key] = $before[$key] ?? ['user_id' => $actor->id, 'checked_at' => now()->toIso8601String()];
            }
            $task->forceFill(['quality_checks' => $checks])->save();
            $state = $this->summary($task)['complete'] ? 'complete' : ($checks ? 'in_progress' : 'not_started');
            app(SaveConcertTrackerTaskFields::class)->execute('event', $task->scope_id, 'quality_check', $actor, ['status' => $state, 'status_override' => $state]);
            if ($before !== $checks) {
                DB::table('concert_tracker_task_changes')->insert([
                    'concert_tracker_task_id' => $task->id, 'user_id' => $actor->id, 'action' => 'quality_checklist_updated',
                    'before' => json_encode(['quality_checks' => $before], JSON_THROW_ON_ERROR),
                    'after' => json_encode(['quality_checks' => $checks], JSON_THROW_ON_ERROR), 'created_at' => now(),
                ]);
            }
        });
    }
}
