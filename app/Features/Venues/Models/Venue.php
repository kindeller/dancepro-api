<?php

namespace App\Features\Venues\Models;

use App\Features\Scheduling\Models\SchedulingEvent;
use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable(['uuid', 'name', 'address_line_1', 'address_line_2', 'suburb', 'state', 'postcode', 'access_notes', 'parking_notes', 'operational_notes', 'map_path', 'reference_image_path'])]
class Venue extends Model
{
    use HasPublicUuid;

    public function schedulingEvents(): HasMany
    {
        return $this->hasMany(SchedulingEvent::class);
    }

    public function mapUrl(): ?string
    {
        return $this->map_path ? Storage::disk('public')->url($this->map_path) : null;
    }

    public function referenceImageUrl(): ?string
    {
        return $this->reference_image_path ? Storage::disk('public')->url($this->reference_image_path) : null;
    }
}
