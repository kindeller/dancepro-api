<?php

namespace App\Features\Scheduling\Actions;

use App\Features\Crew\Models\CrewRole;
use App\Features\Scheduling\Models\SchedulingEvent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class SaveSchedulingEvent
{
    public function __construct(private readonly ResetAssignmentAcknowledgements $resetAcknowledgements) {}

    public function execute(array $data, ?SchedulingEvent $event = null): SchedulingEvent
    {
        return DB::transaction(function () use ($data, $event): SchedulingEvent {
            $event ??= new SchedulingEvent;
            $wasExisting = $event->exists;
            $event->fill(collect($data)->except(['days', 'shifts', 'roles', 'logo'])->all());
            $event->save();
            $materialChanged = $wasExisting && $event->wasChanged(['venue_id', 'event_date']);

            if (($data['logo'] ?? null) instanceof UploadedFile) {
                $event->logo_path = $data['logo']->storeAs(
                    "logos/competitions/{$event->uuid}",
                    'logo.'.$data['logo']->extension(),
                    'public',
                );
                $event->save();
            }

            $roles = collect($data['roles'])->map(fn (string $code) => $this->role($code));
            $removedRoleIds = $event->roleRequirements()->whereNotIn('crew_role_id', $roles->pluck('id'))->pluck('crew_role_id');
            if ($removedRoleIds->isNotEmpty()) {
                $event->shifts()->with('assignments')->get()->flatMap->assignments->whereIn('crew_role_id', $removedRoleIds)->each->delete();
                $materialChanged = true;
            }
            $event->roleRequirements()->whereNotIn('crew_role_id', $roles->pluck('id'))->delete();
            foreach ($roles as $role) {
                $event->roleRequirements()->updateOrCreate(['crew_role_id' => $role->id], ['quantity' => 1]);
            }

            $keptIds = [];

            foreach ($data['shifts'] as $shiftData) {
                $shift = filled($shiftData['uuid'] ?? null)
                    ? $event->shifts()->where('uuid', $shiftData['uuid'])->firstOrFail()
                    : $event->shifts()->make();
                $shift->fill([
                    'shift_date' => $shiftData['shift_date'],
                    'period' => $shiftData['period'],
                    'requires_setup' => $shiftData['requires_setup'],
                    'requires_set_down' => $shiftData['requires_set_down'],
                ]);
                $shift->save();
                $materialChanged = $materialChanged || ($shift->wasRecentlyCreated === false && $shift->wasChanged('shift_date'));
                $keptIds[] = $shift->getKey();

            }

            $event->shifts()->whereNotIn('id', $keptIds)->delete();

            if ($materialChanged) {
                $this->resetAcknowledgements->execute($event, 'The date, venue or role details changed');
            }

            return $event->refresh();
        });
    }

    private function role(string $code): CrewRole
    {
        $names = [
            'competition-videographer' => 'Competition Videographer V',
            'competition-photographer-p1' => 'Competition Photographer P1',
            'competition-photographer-p2' => 'Competition Photographer P2',
        ];

        return CrewRole::query()->firstOrCreate(['code' => $code], ['name' => $names[$code], 'is_active' => true]);
    }
}
