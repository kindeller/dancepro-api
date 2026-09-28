<?php

namespace App\Features\Tracking\Services;

use App\Features\Tracking\Jobs\SendConcertTrackerEmail;
use App\Features\Tracking\Models\ConcertTrackerEmailDelivery;
use Illuminate\Support\Facades\DB;

class ConcertEmailRehearsal
{
    public function capturesLocally(): bool
    {
        return app()->environment('local')
            && config('mail.default') === 'smtp'
            && config('mail.mailers.smtp.host') === 'mailpit'
            && blank(config('mail.mailers.smtp.url'));
    }

    public function simulated(): bool
    {
        return $this->capturesLocally() || in_array(config('mail.default'), ['log', 'array'], true);
    }

    public function dispatch(ConcertTrackerEmailDelivery $delivery): void
    {
        // Only the newly requested immediate message runs synchronously. Never drain old jobs.
        if ($this->capturesLocally() && ! $delivery->scheduled_at) {
            DB::afterCommit(fn () => SendConcertTrackerEmail::dispatchSync($delivery->id));

            return;
        }

        $pending = SendConcertTrackerEmail::dispatch($delivery->id)->afterCommit();
        if ($this->capturesLocally()) {
            $pending->onQueue('concert-rehearsal');
        }
        if ($delivery->scheduled_at) {
            $pending->delay($delivery->scheduled_at);
        }
    }
}
