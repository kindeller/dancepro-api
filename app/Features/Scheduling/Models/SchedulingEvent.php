<?php

namespace App\Features\Scheduling\Models;

use App\Features\Bookings\Models\ConcertBookingItem;
use App\Features\CompetitionContacts\Models\CompetitionContact;
use App\Features\Media\Models\MediaPriceList;
use App\Features\Operations\Models\EventMessage;
use App\Features\Scheduling\Support\AvailabilityRoundStatus;
use App\Features\Scheduling\Support\SchedulingEventType;
use App\Features\Studios\Models\ConcertEventRoleSeats;
use App\Features\Studios\Models\Studio;
use App\Features\Studios\Models\StudioBookingChangeRequest;
use App\Features\Studios\Models\StudioEventAcknowledgement;
use App\Features\Studios\Models\StudioEventFile;
use App\Features\Venues\Models\Venue;
use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

#[Fillable(['uuid', 'venue_id', 'studio_id', 'competition_contact_id', 'event_type_definition_id', 'media_price_list_id', 'name', 'organiser_name', 'organiser_email', 'organiser_phone', 'logo_path', 'event_type', 'event_date', 'availability_status', 'availability_deadline', 'roster_status', 'roster_published_at', 'admin_notes', 'crew_brief', 'team_leader_notes', 'programme_path', 'cancelled_by_user_id', 'cancelled_at'])]
class SchedulingEvent extends Model
{
    use HasPublicUuid;

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }

    public function competitionContact(): BelongsTo
    {
        return $this->belongsTo(CompetitionContact::class);
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(MediaPriceList::class, 'media_price_list_id');
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

    public function roleSeats(): HasMany
    {
        return $this->hasMany(ConcertEventRoleSeats::class);
    }

    public function studioFiles(): HasMany
    {
        return $this->hasMany(StudioEventFile::class)->orderByDesc('uploaded_at');
    }

    public function studioBookingChangeRequests(): HasMany
    {
        return $this->hasMany(StudioBookingChangeRequest::class)->latest();
    }

    public function studioEventAcknowledgements(): HasMany
    {
        return $this->hasMany(StudioEventAcknowledgement::class);
    }

    public function concertBookingItem(): HasOne
    {
        return $this->hasOne(ConcertBookingItem::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(EventMessage::class);
    }

    public function eventCode(): ?string
    {
        if (! $this->event_date) {
            return null;
        }

        $competition = $this->event_type === SchedulingEventType::Competition;
        $code = trim(($competition ? $this->competitionContact?->code : $this->studio?->code) ?? '');

        return $code !== '' ? $code.$this->event_date->format($competition ? 'ny' : 'Y') : null;
    }

    public function crewChatTitle(): string
    {
        if ($this->event_type === SchedulingEventType::Concert && $this->event_date !== null) {
            $code = trim($this->studio?->code ?? '');

            return ($code !== '' ? $code : $this->name).' '.$this->event_date->format('j/n');
        }

        return $this->eventCode() ?? $this->name;
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk(config('uploads.public_disk'))->url($this->logo_path) : null;
    }

    public function crewLogoUrl(): ?string
    {
        $logoUrl = match ($this->event_type) {
            SchedulingEventType::Concert => $this->studio?->logoUrl(),
            SchedulingEventType::Competition => $this->competitionContact?->logoUrl(),
        } ?? $this->logoUrl();

        return $logoUrl !== null ? url($logoUrl) : null;
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
        return ['website_visible' => 'boolean',
            'website_hidden' => 'boolean',
            'event_type' => SchedulingEventType::class,
            'event_date' => 'date',
            'availability_status' => AvailabilityRoundStatus::class,
            'availability_deadline' => 'datetime',
            'roster_published_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
