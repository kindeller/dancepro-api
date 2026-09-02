<?php

namespace App\Features\Crew\Models;

use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['uuid', 'recognition_type_id', 'crew_profile_id', 'scheduling_event_id', 'awarded_by_user_id', 'title', 'message', 'icon', 'design', 'awarded_on', 'show_on_profile'])]
class CrewRecognition extends Model
{
    use HasPublicUuid;

    public function type(): BelongsTo
    {
        return $this->belongsTo(RecognitionType::class, 'recognition_type_id');
    }

    public function crewProfile(): BelongsTo
    {
        return $this->belongsTo(CrewProfile::class);
    }

    public function schedulingEvent(): BelongsTo
    {
        return $this->belongsTo(SchedulingEvent::class);
    }

    public function awardedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'awarded_by_user_id');
    }

    protected function casts(): array
    {
        return ['awarded_on' => 'date', 'show_on_profile' => 'boolean'];
    }
}
