<?php

namespace App\Features\CompetitionContacts\Models;

use App\Features\Scheduling\Models\SchedulingEvent;
use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

#[Fillable(['uuid', 'name', 'code', 'logo_path', 'organiser_name', 'organiser_email', 'organiser_phone', 'is_active', 'notes'])]
class CompetitionContact extends Model
{
    use HasPublicUuid, SoftDeletes;

    public function events(): HasMany
    {
        return $this->hasMany(SchedulingEvent::class);
    }

    public function staff(): HasMany
    {
        return $this->hasMany(CompetitionContactStaff::class)->orderBy('position')->orderBy('id');
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk(config('contact-directory.logo_disk'))->url($this->logo_path) : null;
    }

    /** @return list<string> */
    public function contactEmailAddresses(): array
    {
        return $this->staff
            ->flatMap(fn (CompetitionContactStaff $staff): array => $staff->emailAddresses())
            ->unique()
            ->values()
            ->all();
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
