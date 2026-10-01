<?php

namespace App\Features\Tracking\Services;

use App\Features\Customers\Models\GuestStudioSubscription;
use App\Features\Media\Models\ConcertPhotoReleaseGroup;
use App\Features\Publicity\Services\PublicityEventAccess;
use App\Features\Tracking\Models\ConcertTrackerEmailDelivery;
use App\Features\Tracking\Models\StudioConversationMessage;
use Illuminate\Support\Facades\URL;

class PhotoReleaseMessageContent
{
    public function guest(ConcertPhotoReleaseGroup $group, string $eventList): array
    {
        $gallery = route('guest-galleries.studios.show', $group->studio);

        $name = $group->studio->name;
        $body = <<<MARKDOWN
Hi everyone,

The new **{$name} concert photos** are ready to view and order.

**Events included:**
{$eventList}

[View the studio galleries]({$gallery})

## Browse the photos

Enter your email address and the studio access code supplied by your studio. You can then move between the studio’s released concert events without entering a separate code for each event. Choose an event and album to browse the photos and add favourites or items to your cart.

## Ready to order?

Sign in to your DancePro account, or choose Create account and verify your email. Your guest favourites and cart will transfer when you sign in using the same browser and email address. Complete your purchase; when your order is ready, the photos will be in My Media → My Purchases for download.

Your studio is also added to your account when your guest activity transfers. If it is not listed, open My Profile → My Studio and add it using the studio access code. This keeps the released concert galleries together in My Events.

Forgotten the studio code? Please ask your studio. Concert videos use separate access details and are released separately.

For help, visit the Help page or send us a message from your DancePro account.
MARKDOWN;

        return ['subject' => 'Your '.$name.' concert photos are ready', 'body' => $body];
    }

    public function studio(ConcertPhotoReleaseGroup $group, string $eventList): array
    {
        $name = $group->studio->name;
        $join = app(PublicityEventAccess::class)->studioLink($group->studio);
        $body = <<<MARKDOWN
Hi {{ first_name }},

Your concert photos are now available. Please send the family message below to your dance families. Customers already linked to your studio and eligible gallery guests receive their own announcement through this release.

**Events included:**
{$eventList}

## Message to share with your dance families

Your {$name} concert photos are ready to view and order on DancePro.

{$eventList}

[Sign in or create your DancePro account and add our studio]({$join})

- Already have a DancePro account? Sign in using the link above.
- New to DancePro? Choose Create account, enter your details and verify your email.
- The link adds our studio to your account automatically, so you do not need to enter a studio or gallery code.
- Open My Media → My Events, choose our studio, then the event and album you want to browse.
- Add favourites, add photos to your cart and complete your purchase. Purchased photos appear in My Purchases for download.

Please share this link only with our dance families. It gives access to our released concert photo galleries, not Studio Hub or concert videos.

If you need help, use Help or My Messages in your DancePro account.
MARKDOWN;

        return ['subject' => $name.' concert photos are ready to share', 'body' => $body];
    }

    public function personalise(array $message, string $firstName): array
    {
        $firstName = trim(preg_replace('/[\r\n\t]+/u', ' ', $firstName)) ?: 'there';
        $tokens = ['{{ first_name }}', '{{first_name}}'];
        $message['subject'] = str_replace($tokens, $firstName, $message['subject']);
        $message['body'] = str_replace($tokens, addcslashes($firstName, '\\`*_{}[]()<>#'), $message['body']);

        return $message;
    }

    public function action(ConcertTrackerEmailDelivery $delivery): array
    {
        $audience = data_get($delivery->workflow_context, 'photo_release_audience', 'customer');
        $studio = $delivery->event->studio;

        return match ($audience) {
            'studio' => [route('customer.studio-communications.index', ['studio' => $studio, 'tab' => 'messages']), 'Open Studio Messages', 'Your release announcement and family instructions are saved in Studio Messages.'],
            'guest' => [route('guest-galleries.studios.show', $studio), 'View studio galleries', 'Enter your email and the studio access code to browse. Sign in or create an account when you are ready to order.'],
            default => [route('customer.profile.studio', $studio), 'Open My Events', 'Sign in to view and order your studio’s released concert photos.'],
        };
    }

    public function guestFooter(ConcertPhotoReleaseGroup $group, string $email): string
    {
        $subscription = GuestStudioSubscription::firstOrCreate(['studio_id' => $group->studio_id, 'email_hash' => hash('sha256', $email)], ['email' => $email, 'consent_at' => now()]);
        $url = URL::temporarySignedRoute('guest-galleries.studios.unsubscribe', now()->addMonths(3), ['subscription' => $subscription]);

        return "\n\nYou are receiving this gallery update because you registered for studio updates or previously used the studio access code to browse its galleries. [Unsubscribe from studio gallery updates](".$url.').';
    }

    public function sent(ConcertTrackerEmailDelivery $delivery): void
    {
        if (data_get($delivery->workflow_context, 'photo_release_audience') !== 'studio') {
            return;
        }
        // The release row serialises this once-only Studio Messages entry.
        $group = ConcertPhotoReleaseGroup::whereKey($delivery->photo_release_group_id)->lockForUpdate()->firstOrFail();
        $channel = 'photo_release';
        StudioConversationMessage::firstOrCreate(['studio_id' => $group->studio_id, 'channel' => $channel, 'photo_release_group_id' => $group->id], [
            'scheduling_event_id' => $delivery->scheduling_event_id, 'direction' => 'from_dancepro',
            'sender_user_id' => $delivery->created_by_user_id, 'sender_name' => 'DancePro',
            'subject' => $delivery->subject, 'body' => $delivery->body, 'sent_at' => $delivery->sent_at,
        ]);
    }
}
