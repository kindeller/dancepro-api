<?php

namespace App\Features\Scheduling\Models;

use App\Features\Bookings\Models\ConcertBookingItem;
use App\Features\CompetitionContacts\Models\CompetitionContact;
use App\Features\Operations\Models\EventMessage;
use App\Features\Scheduling\Support\AvailabilityRoundStatus;
use App\Features\Scheduling\Support\SchedulingEventType;
use App\Features\Venues\Models\Venue;
use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

#[Fillable(['uuid', 'venue_id', 'competition_contact_id', 'event_type_definition_id', 'name', 'organiser_name', 'organiser_email', 'organiser_phone', 'logo_path', 'event_type', 'event_date', 'availability_status', 'availability_deadline', 'roster_status', 'roster_published_at', 'admin_notes', 'crew_brief', 'team_leader_notes', 'programme_path'])]
class SchedulingEvent extends Model
{
    use HasPublicUuid;

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function competitionContact(): BelongsTo
    {
        return $this->belongsTo(CompetitionContact::class);
    }

    public function eventTypeDefinition(): BelongsTo
    {
        return $this->belongsTo(EventTypeDefinition::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(SchedulingShift::class);
    }

    public function roleRequirements(): HasMany
    {
        return $this->hasMany(SchedulingEventRoleRequirement::class);
    }

    public function concertBookingItem(): HasOne
    {
        return $this->hasOne(ConcertBookingItem::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(EventMessage::class);
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk(config('uploads.public_disk'))->url($this->logo_path) : null;
    }

    public function programmeUrl(): ?string
    {
        return $this->programme_path ? route('internal-documents.events.programme', $this) : null;
    }

    public function rosterIsReady(): bool
    {
        $this->loadMissing(['roleRequirements', 'shifts.assignments']);
        if ($this->shifts->isEmpty() || $this->roleRequirements->isEmpty()) {
            return false;
        }

        $requiredRoleIds = $this->roleRequirements->pluck('crew_role_id');

        return $this->shifts->every(function (SchedulingShift $shift) use ($requiredRoleIds): bool {
            $assignments = $shift->assignments;

            return $requiredRoleIds->diff($assignments->pluck('crew_role_id'))->isEmpty()
                && $assignments->isNotEmpty()
                && $assignments->every(fn (SchedulingShiftAssignment $assignment): bool => $assignment->status === 'published' && $assignment->acknowledgement_status === 'acknowledged');
        });
    }

    protected function casts(): array
    {
        return [
            'event_type' => SchedulingEventType::class,
            'event_date' => 'date',
            'availability_status' => AvailabilityRoundStatus::class,
            'availability_deadline' => 'datetime',
            'roster_published_at' => 'datetime',
        ];
    }
}
