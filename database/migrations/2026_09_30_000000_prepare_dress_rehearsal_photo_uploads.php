<?php

use App\Features\Programmes\Actions\PrepareDressRehearsalPhotos;
use App\Features\Scheduling\Models\SchedulingEvent;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        SchedulingEvent::whereNull('cancelled_at')->whereHas('concertBookingItem', fn ($query) => $query
            ->where('item_type', 'dress_rehearsal')->where('approval_status', 'approved')->whereNull('cancelled_at'))
            ->chunkById(100, function ($events): void {
                foreach ($events as $event) {
                    app(PrepareDressRehearsalPhotos::class)->execute($event);
                }
            });
    }

    public function down(): void
    {
        // Keep preparation identities: uploaded photos may already refer to them.
    }
};
