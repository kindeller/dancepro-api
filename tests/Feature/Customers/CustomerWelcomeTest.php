<?php

namespace Tests\Feature\Customers;

use App\Features\Customers\Models\CustomerAccountEmail;
use App\Features\Customers\Models\CustomerInboxNotification;
use App\Features\Customers\Models\CustomerSupportRequest;
use App\Features\Customers\Services\CustomerInbox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CustomerWelcomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_verification_sends_one_branded_welcome_and_creates_one_unread_notification(): void
    {
        config(['mail.default' => 'array', 'mail.from.address' => 'notifications@dancepro.com.au']);
        $user = User::factory()->customer()->unverified()->create(['first_name' => 'Jess', 'last_name' => 'Sample']);
        $url = $this->verificationUrl($user);
        $this->actingAs($user)->get($url)->assertRedirect(route('customer.media.index'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $email = CustomerAccountEmail::sole();
        $this->assertSame('welcome', $email->metadata['kind']);
        $this->assertSame('sent', $email->status);
        $entry = CustomerInboxNotification::sole();
        $this->assertSame($user->id, $entry->user_id);
        $this->assertSame($email->body, $entry->body);
        $this->assertNull($entry->read_at);
        $this->assertSame(1, app(CustomerInbox::class)->counts($user)['total']);
        $this->assertSame(0, CustomerSupportRequest::count());
        $sent = Mail::mailer()->getSymfonyTransport()->messages();
        $this->assertCount(1, $sent);
        $message = $sent->first()->getOriginalMessage();
        $this->assertSame('notifications@dancepro.com.au', $message->getFrom()[0]->getAddress());
        $this->assertStringContainsString('Hi Jess,', $message->getHtmlBody());
        $this->assertStringContainsString('dancepro-logo-inverse-landscape.png', $message->getHtmlBody());
        foreach (['My Family', 'My studio', 'My Purchases', 'My Orders', 'Notifications', 'Save & send invite'] as $text) {
            $this->assertStringContainsString($text, $entry->body);
        }
        $this->assertStringNotContainsString('DancePro on your phone', $message->getHtmlBody());
        $this->get($url)->assertRedirect();
        $this->assertSame(1, CustomerAccountEmail::count());
        $this->assertSame(1, CustomerInboxNotification::count());
        $this->assertCount(1, Mail::mailer()->getSymfonyTransport()->messages());
        $this->get(route('customer.messages.index'))->assertOk()->assertSee($email->subject);
    }

    public function test_invalid_or_expired_verification_and_already_verified_accounts_do_not_get_a_welcome(): void
    {
        Notification::fake();
        $user = User::factory()->customer()->unverified()->create();
        $this->get(URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1('wrong@example.test')]))->assertForbidden();
        $this->get(URL::temporarySignedRoute('verification.verify', now()->subMinute(), ['id' => $user->id, 'hash' => sha1($user->email)]))->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $user->markEmailAsVerified();
        $this->get($this->verificationUrl($user))->assertRedirect(route('login'));
        $this->assertSame(0, CustomerAccountEmail::count());
        Notification::assertNothingSent();
    }

    public function test_failed_delivery_keeps_verification_and_inbox_welcome_and_can_retry_without_duplicates(): void
    {
        $user = User::factory()->customer()->unverified()->create();
        Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('Test mail unavailable'));
        $url = $this->verificationUrl($user);
        $this->get($url)->assertRedirect(route('login'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertSame('failed', CustomerAccountEmail::sole()->status);
        $this->assertSame('failed', CustomerInboxNotification::sole()->email_status);
        Notification::fake();
        $this->get($url)->assertRedirect(route('login'));
        $this->assertSame('sent', CustomerAccountEmail::sole()->status);
        $this->assertSame(1, CustomerInboxNotification::count());
    }

    public function test_published_app_links_are_optional_and_names_are_escaped_in_html(): void
    {
        config(['mail.default' => 'array', 'customer.mobile_apps.ios_url' => 'https://apps.apple.com/example',
            'customer.mobile_apps.android_url' => 'https://play.google.com/example']);
        $user = User::factory()->customer()->unverified()->create(['first_name' => '<script>Jess</script>', 'last_name' => 'Sample']);
        $this->get($this->verificationUrl($user))->assertRedirect();
        $message = Mail::mailer()->getSymfonyTransport()->messages()->sole()->getOriginalMessage();
        $this->assertStringContainsString('DancePro on your phone', $message->getHtmlBody());
        $this->assertStringNotContainsString('<script>Jess</script>', $message->getHtmlBody());
        $this->assertStringContainsString('https://apps.apple.com/example', $message->getTextBody());
        $this->assertStringContainsString('https://play.google.com/example', CustomerInboxNotification::sole()->body);
    }

    private function verificationUrl(User $user): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);
    }
}
