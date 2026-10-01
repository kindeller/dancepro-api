<?php

namespace Tests\Feature\Customers;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_verification_uses_branded_html_and_plain_text_with_the_original_signed_link(): void
    {
        $this->freezeTime();
        $user = User::factory()->customer()->unverified()->create(['name' => '<script>alert(1)</script>']);
        $message = (new VerifyEmail)->toMail($user);
        $data = $message->viewData;
        $url = $data['verificationUrl'];

        $this->assertSame('Verify your email address', $message->subject);
        $this->assertTrue(URL::hasValidSignature(Request::create($url)));
        $this->assertStringContainsString('/customer/email/verify/'.$user->id.'/', $url);
        $this->assertStringContainsString('expires='.now()->addMinutes(60)->timestamp, $url);
        $html = view($message->view['html'], $data)->render();
        $this->assertStringContainsString('dancepro-logo-inverse-landscape.png', $html);
        $this->assertStringContainsString('Your DancePro account', $html);
        $this->assertStringContainsString('background:#0aa0db', $html);
        $this->assertStringContainsString('href="'.e($url).'"', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('DancePro V2', $html);
        $text = view($message->view['text'], $data)->render();
        $this->assertStringContainsString($url, $text);
        $this->assertStringContainsString('expires in 60 minutes', $text);
        $this->travel(61)->minutes();
        $this->assertFalse(URL::hasValidSignature(Request::create($url)));
    }

    public function test_resending_delivers_both_versions_with_the_configured_sender_without_verifying_the_account(): void
    {
        config(['mail.default' => 'array', 'mail.from.address' => 'notifications@dancepro.com.au', 'mail.from.name' => 'DancePro']);
        $user = User::factory()->customer()->unverified()->create();
        $this->actingAs($user)->post(route('customer.verification.resend'))->assertRedirect();

        $sent = Mail::mailer()->getSymfonyTransport()->messages();
        $this->assertCount(1, $sent);
        $email = $sent->first()->getOriginalMessage();
        $this->assertSame('notifications@dancepro.com.au', $email->getFrom()[0]->getAddress());
        $this->assertSame('DancePro', $email->getFrom()[0]->getName());
        $this->assertStringContainsString('Your DancePro account', $email->getHtmlBody());
        $this->assertStringContainsString('Verify your email address', $email->getTextBody());
        $this->assertNull($user->fresh()->email_verified_at);
    }
}
