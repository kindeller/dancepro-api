<?php

namespace App\Features\Notifications\Jobs;

use App\Features\Notifications\Services\MobilePushDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeliverMobilePushNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $notificationId) {}

    public function handle(MobilePushDelivery $delivery): void
    {
        $delivery->deliver($this->notificationId);
    }
}
