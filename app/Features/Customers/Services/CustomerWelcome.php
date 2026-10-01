<?php

namespace App\Features\Customers\Services;

use App\Features\Customers\Models\CustomerAccountEmail;
use App\Features\Customers\Notifications\CustomerWelcomeNotification;
use App\Models\User;

class CustomerWelcome
{
    public function prepare(User $user): CustomerAccountEmail
    {
        $data = $this->content($user);

        return CustomerAccountEmail::create([
            'user_id' => $user->id, 'recipient_email' => $user->email,
            'subject' => 'Welcome to DancePro — your account is ready',
            'body' => view('emails.customers.welcome-text', $data)->render(),
            'status' => 'queued', 'queued_at' => now(),
            'metadata' => ['kind' => 'welcome', 'welcome' => $data],
        ]);
    }

    public function send(CustomerAccountEmail $email): void
    {
        if (! CustomerAccountEmail::whereKey($email->id)->whereIn('status', ['queued', 'failed'])->update(['status' => 'sending'])) {
            return;
        }
        $user = $email->user;
        if (! $user?->canAccessCustomer() || ! $user->hasVerifiedEmail() || $user->email !== $email->recipient_email) {
            $email->update(['status' => 'cancelled']);

            return;
        }
        try {
            $user->notify(new CustomerWelcomeNotification($email));
            $email->update(['status' => 'sent', 'sent_at' => now(), 'failed_at' => null]);
        } catch (\Throwable $exception) {
            report($exception);
            $email->update(['status' => 'failed', 'failed_at' => now()]);
            // Verification succeeds and the welcome remains available in My Messages.
        }
    }

    private function content(User $user): array
    {
        $sections = [
            ['title' => '1. Check your details', 'body' => 'Open My Profile → My details to check your name, preferred name, phone and email. You can edit these later. Changing your email requires confirmation of the new address.', 'label' => 'Open My Profile', 'url' => route('customer.profile.edit')],
            ['title' => '2. Add your studio', 'body' => 'In My Profile → My studio, search for your dance studio and enter its studio code. If it is not listed or has no code, submit its name for DancePro to confirm and link to your account. Studio membership includes its concert photo galleries when released. Competitions require an event code, and concert videos use the video code supplied by your studio after release. A studio inherited from your family will already appear here. Gallery access does not automatically include purchases or unreleased media.', 'label' => 'Add your studio', 'url' => route('customer.profile.edit', ['tab' => 'studios'])],
            ['title' => '3. Set up My Family', 'body' => 'Tick Dancer for yourself if you perform, or use + Add family member to add your dancers. Add alternative programme spellings under Competition search names to help find routines. Dancer-only profiles need no email and cannot sign in. To give someone their own access, select their media or purchase permissions, enter their own distinct email and choose Save & send invite. They must accept the invitation using a verified account. Accepted members inherit the account manager’s studios and events; eligible family purchases are shared according to permissions. Each person keeps their own favourites, cart, orders and payment details. If you have been invited to a family, return to that invitation and accept it first.', 'label' => 'Set up My Family', 'url' => route('customer.profile.edit', ['tab' => 'family'])],
            ['title' => '4. Find your events and media', 'body' => 'My Media → My Events brings your galleries together. Use Add media to add a competition or concert video with the access details supplied by the event or studio. Search competition routines using your dancers’ names. Your studio’s concert photos appear when released. Concert videos become available after the studio releases them and shares the video access code.', 'label' => 'Open My Media', 'url' => route('customer.media.index')],
            ['title' => '5. Save favourites, order and enjoy', 'body' => 'Save favourites while browsing, add the photos or videos you want to your cart, then check your selection before paying. My Purchases contains your purchased photos and videos, with Photos/Videos filters. My Orders holds order history and receipts. Stream available concert videos or use their download controls to save individual videos or start multiple downloads. Follow the access and expiry information shown for your media. Where available, Share with family creates a separate concert video link with its own expiry and download limits; keep that link private. Family members can purchase or access shared purchases only when their permissions allow it.', 'label' => null, 'url' => null],
            ['title' => '6. Stay up to date and get help', 'body' => 'My Messages → Notifications keeps automated updates, including this welcome. Conversations holds personal messages and DancePro replies; use New message to contact us. Unread items show a numbered menu badge. In My Profile → Email preferences, choose which media updates, reminders and news you receive. Essential account and order messages still apply. Help includes guides for finding, viewing and downloading your media.', 'label' => 'Open My Messages', 'url' => route('customer.messages.index')],
        ];
        $apps = array_filter([
            'Download for iPhone / iPad' => config('customer.mobile_apps.ios_url'),
            'Download for Android' => config('customer.mobile_apps.android_url'),
        ], fn ($url) => is_string($url) && filter_var($url, FILTER_VALIDATE_URL) && str_starts_with($url, 'https://'));

        return ['firstName' => $user->emailGreetingName(), 'sections' => $sections, 'apps' => $apps,
            'accountUrl' => route('customer.media.index'), 'helpUrl' => route('customer.media.help')];
    }
}
