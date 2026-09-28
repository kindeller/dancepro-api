<?php

namespace App\Features\Tracking\Actions;

use App\Features\Tracking\Jobs\SendConcertTrackerEmail;
use App\Features\Tracking\Models\ConcertTrackerEmailDelivery;
use App\Features\Tracking\Services\ConcertEmailRehearsal;
use Illuminate\Support\Carbon;
use RuntimeException;

class RehearseConcertEmail
{
    public function __construct(private readonly ConcertEmailRehearsal $rehearsal) {}

    public function execute(string $uuid, ?string $at = null): ConcertTrackerEmailDelivery
    {
        if (! $this->rehearsal->capturesLocally()) {
            throw new RuntimeException('This command requires the local Mailpit capture transport.');
        }
        $delivery = ConcertTrackerEmailDelivery::where('uuid', $uuid)->firstOrFail();
        if (! $delivery->is_simulated) {
            throw new RuntimeException('Only explicitly simulated messages can be rehearsed.');
        }

        // The clock changes only inside this call, for this delivery. No scheduler or queue drain.
        Carbon::withTestNow($at ? Carbon::parse($at) : now(), function () use ($delivery): void {
            (new SendConcertTrackerEmail($delivery->id))->handle();
        });

        return $delivery->refresh();
    }
}
