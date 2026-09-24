<?php

namespace App\Features\Programmes\Services;

use App\Features\Operations\Actions\PostEventMessage;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;

class NotifySharedProgramme
{
    public function __construct(private readonly PostEventMessage $messages) {}

    public function updates(SchedulingEvent $event): array
    {
        return $event->messages()->where('message_type', 'programme_update')->with('author')
            ->latest('id')->limit(50)->get()->reverse()->values()->map(fn ($message) => [
                'uuid' => $message->uuid, 'body' => $message->body,
                'author' => $message->author->name, 'created_at' => $message->created_at->toIso8601String(),
            ])->all();
    }

    public function competition(SchedulingEvent $event, User $actor, string $heading, array $changes): void
    {
        $summary = implode("\n", array_slice($changes, 0, 20));
        if (count($changes) > 20) {
            $summary .= "\nPlus ".(count($changes) - 20).' more changes.';
        }
        $this->messages->execute($event, $actor, 'programme_update', $heading." (automatic)\n".$summary."\nP1/P2/V: check folder differences in the uploader. Local folders have not been renamed.", null);
    }

    public function send(SchedulingEvent $event, User $actor, array $before, array $after): void
    {
        $old = collect($before)->keyBy('uuid');
        $changes = [];
        foreach ($after as $node) {
            $previous = $old->get($node['uuid']);
            if (! $previous) {
                $changes[] = 'Added: '.$node['title'];
            } elseif (($previous['merged_into_uuid'] ?? null) !== ($node['merged_into_uuid'] ?? null)) {
                $changes[] = 'Combined: '.$previous['title'];
            } elseif ($previous['title'] !== $node['title']) {
                $changes[] = 'Renamed: '.$previous['title'].' → '.$node['title'];
            } elseif (($previous['folder_number'] ?? null) !== ($node['folder_number'] ?? null)) {
                $changes[] = 'Renumbered: '.$node['title'].' to '.($node['folder_number'] ?? '');
            }
        }
        $summary = $changes === [] ? 'Programme confirmed for the crew.' : implode("\n", array_slice($changes, 0, 20));
        if (count($changes) > 20) {
            $summary .= "\nPlus ".(count($changes) - 20).' more changes.';
        }
        $this->messages->execute($event, $actor, 'programme_update', "Programme update (automatic)\n".$summary."\nReady to load in the uploader: open Programme. Confirmed means this Mac has the shared version.", null);
    }
}
