<?php

namespace App\Features\Tracking\Actions;

use App\Features\Tracking\Mail\ConcertTrackerMessage;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class SendEmailBrandingTest
{
    public function execute(User $recipient): void
    {
        $body = <<<'MARKDOWN'
## Your DancePro email preview

Hi there,

This is a sample studio email using your saved **branding, formatting and staff signature**.

### Sample event details

- **Event:** Spring Concert 2026
- **Concert date:** Saturday, 28 November 2026
- **Venue:** Sample Performing Arts Centre
- **Arrival:** 5:30 pm

> This preview shows how highlighted information and account links will appear to a studio contact.

Please log into your DancePro account for more details, messages and event updates.
MARKDOWN;

        Mail::to($recipient->email, $recipient->name)->send(new ConcertTrackerMessage(
            'DancePro branded email preview',
            $body,
            null,
            route('admin.concert-tracker.automation.settings'),
            $recipient->id,
        ));
    }
}
