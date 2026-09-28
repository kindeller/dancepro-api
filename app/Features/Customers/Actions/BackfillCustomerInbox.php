<?php

namespace App\Features\Customers\Actions;

use App\Features\Customers\Models\CustomerAccountEmail;
use App\Features\Customers\Models\CustomerSupportRequest;
use App\Features\Customers\Services\CustomerConversations;
use App\Features\Customers\Services\CustomerInboxSources;
use App\Features\Payments\Models\CustomerMediaNotification;
use App\Features\Tracking\Models\ConcertTrackerEmailDelivery;
use Illuminate\Notifications\DatabaseNotification;

class BackfillCustomerInbox
{
    public function execute(): void
    {
        $sources = app(CustomerInboxSources::class);
        foreach ([CustomerMediaNotification::class, CustomerAccountEmail::class, DatabaseNotification::class] as $model) {
            $model::query()->chunkById(200, function ($rows) use ($sources): void {
                foreach ($rows as $row) {
                    $sources->sync($row);
                }
            });
        }
        ConcertTrackerEmailDelivery::whereNotNull('photo_release_group_id')->chunkById(200, function ($rows) use ($sources): void {
            foreach ($rows as $row) {
                $sources->sync($row);
            }
        });
        CustomerSupportRequest::query()->chunkById(200, function ($requests): void {
            foreach ($requests as $request) {
                app(CustomerConversations::class)->syncHistory($request);
            }
        });
    }
}
