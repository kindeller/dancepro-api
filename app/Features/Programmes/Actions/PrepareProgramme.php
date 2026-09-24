<?php

namespace App\Features\Programmes\Actions;

use App\Features\Programmes\Models\Programme;
use App\Features\Programmes\Services\ProgrammeSourceStorage;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PrepareProgramme
{
    public function __construct(private readonly ProgrammeSourceStorage $sources) {}

    public function execute(SchedulingEvent $event, User $user, ?UploadedFile $upload = null): Programme
    {
        return DB::transaction(function () use ($event, $user, $upload): Programme {
            SchedulingEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $programme = Programme::firstOrCreate(['scheduling_event_id' => $event->id], ['updated_by_user_id' => $user->id]);
            if (! $programme->wasRecentlyCreated) {
                abort_if($upload !== null, 409, 'Preparation already exists. Open it to upload a replacement PDF.');
                app(QueueProgrammeExtraction::class)->execute($programme);

                return $programme;
            }
            $source = $this->sources->capture($programme, $user, $upload);
            $programme->update(['programme_source_id' => $source?->id]);
            if ($event->event_type->value === 'competition') {
                $dates = $event->shifts()->orderBy('shift_date')->pluck('shift_date')->map(fn ($date) => substr((string) $date, 0, 10))->unique()->values();
                if ($dates->isEmpty() && $event->event_date) {
                    $dates->push($event->event_date->toDateString());
                }
                foreach ($dates as $index => $date) {
                    $uuid = (string) Str::uuid();
                    $programme->nodes()->create(['uuid' => $uuid, 'kind' => 'day', 'sort_order' => $index, 'data' => [
                        'uuid' => $uuid, 'parent_uuid' => null, 'kind' => 'day', 'title' => 'Day '.($index + 1), 'date' => $date,
                    ]]);
                }
            }

            app(QueueProgrammeExtraction::class)->execute($programme);

            return $programme;
        });
    }
}
