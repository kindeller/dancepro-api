<?php

namespace App\Features\Concerts\Actions;

use App\Features\Concerts\Models\Concert;
use App\Features\Concerts\Models\ConcertCodeGroup;
use App\Features\Concerts\Models\ConcertCodeGroupChange;
use App\Features\Studios\Models\Studio;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ManageConcertCodeGroup
{
    public function create(Studio $studio, User $actor, array $input): ConcertCodeGroup
    {
        try {
            return DB::transaction(function () use ($studio, $actor, $input): ConcertCodeGroup {
                $concerts = $this->availableConcerts($studio, (int) $input['year'], $input['concert_uuids']);
                $group = ConcertCodeGroup::create([
                    'studio_id' => $studio->id, 'year' => $input['year'], 'name' => trim($input['name']),
                    'code_hash' => Hash::make(strtoupper(trim($input['code']))), 'created_by_user_id' => $actor->id,
                    'code_encrypted' => strtoupper(trim($input['code'])), 'version' => 1,
                ]);
                Concert::whereIn('id', $concerts->pluck('id'))->update(['concert_code_group_id' => $group->id]);
                $this->record($group, $actor, 'created');

                return $group;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => 'A video code group with this name already exists for this studio and year.']);
        }
    }

    public function addConcerts(ConcertCodeGroup $group, User $actor, array $input): void
    {
        DB::transaction(function () use ($group, $actor, $input): void {
            $group = ConcertCodeGroup::whereKey($group->id)->lockForUpdate()->firstOrFail();
            $this->checkVersion($group, $input);
            $concerts = $this->availableConcerts($group->studio, $group->year, $input['concert_uuids']);
            if ($group->released_at && $concerts->contains(fn (Concert $concert) => $concert->available_until && $concert->available_until->lt($group->downloadEndsAt()))) {
                throw ValidationException::withMessages(['concert_uuids' => 'A concert availability end date is earlier than this group’s download period.']);
            }
            Concert::whereIn('id', $concerts->pluck('id'))->update(['concert_code_group_id' => $group->id]);
            $group->increment('version');
            $this->record($group, $actor, 'concerts_added');
        });
    }

    public function release(ConcertCodeGroup $group, User $actor, array $input): void
    {
        DB::transaction(function () use ($group, $actor, $input): void {
            $group = ConcertCodeGroup::whereKey($group->id)->lockForUpdate()->firstOrFail();
            $this->checkVersion($group, $input);
            $concerts = $group->concerts()->with('bookingItem')->get();
            if ($group->released_at !== null || ! $concerts->contains(fn (Concert $concert) => $concert->isPubliclyAvailable())) {
                throw ValidationException::withMessages(['version' => 'This group cannot be released. Reload and check its concerts.']);
            }
            if ($concerts->contains(fn (Concert $concert) => $concert->available_until && $concert->available_until->lt(now()->addMonthsNoOverflow(12)))) {
                throw ValidationException::withMessages(['version' => 'A concert availability end date is earlier than the 12-month download period.']);
            }
            $group->released_at = now();
            $group->version++;
            $group->save();
            $this->record($group, $actor, 'released');
        });
    }

    public function rotate(ConcertCodeGroup $group, User $actor, array $input): void
    {
        DB::transaction(function () use ($group, $actor, $input): void {
            $group = ConcertCodeGroup::whereKey($group->id)->lockForUpdate()->firstOrFail();
            $this->checkVersion($group, $input);
            $group->code_hash = Hash::make(strtoupper(trim($input['code'])));
            $group->code_encrypted = strtoupper(trim($input['code']));
            $group->version++;
            $group->save();
            $this->record($group, $actor, 'code_changed');
        });
    }

    private function record(ConcertCodeGroup $group, User $actor, string $action): void
    {
        ConcertCodeGroupChange::create(['concert_code_group_id' => $group->id, 'actor_user_id' => $actor->id,
            'action' => $action, 'version' => $group->version, 'created_at' => now()]);
    }

    private function availableConcerts(Studio $studio, int $year, array $uuids)
    {
        $concerts = Concert::whereIn('uuid', $uuids)->lockForUpdate()->get();
        if ($concerts->count() !== count($uuids) || $concerts->contains(fn (Concert $concert) => $concert->studio_id !== $studio->id
            || $concert->event_date?->year !== $year || $concert->concert_code_group_id !== null)) {
            throw ValidationException::withMessages(['concert_uuids' => 'Choose unassigned concerts from this studio and year.']);
        }

        return $concerts;
    }

    private function checkVersion(ConcertCodeGroup $group, array $input): void
    {
        if ($group->version !== (int) $input['version']) {
            throw ValidationException::withMessages(['version' => 'This group changed in another window. Reload and try again.']);
        }
    }
}
