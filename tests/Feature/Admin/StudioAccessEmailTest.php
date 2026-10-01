<?php

namespace Tests\Feature\Admin;

use App\Features\Studios\Actions\ManageStudioAccountAccess;
use App\Features\Studios\Models\Studio;
use App\Features\Studios\Notifications\StudioAccountInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StudioAccessEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_owner_gets_branded_access_added_email_with_first_name_and_direct_hub_link(): void
    {
        Notification::fake();
        $user = User::factory()->customer()->create(['name' => 'Jesstudio Ownerberg']);
        $studio = Studio::factory()->create(['name' => 'Energy Dance Studio']);
        app(ManageStudioAccountAccess::class)->invite($studio, User::factory()->admin()->create(), ['email' => $user->email, 'role' => 'owner']);
        Notification::assertSentOnDemand(StudioAccountInvitation::class, function ($notification, $channels, $notifiable) use ($studio): bool {
            $message = $notification->toMail($notifiable);
            $html = view($message->view['html'], $message->viewData)->render();
            $this->assertSame('Studio Hub access added: Energy Dance Studio', $message->subject);
            $this->assertStringContainsString('Hi Jesstudio,', $html);
            $this->assertStringNotContainsString('Hi Jesstudio Ownerberg', $html);
            $this->assertStringNotContainsString('been invited', $html);
            $this->assertStringContainsString('dancepro-logo-inverse-landscape.png', $html);
            $this->assertStringContainsString(route('customer.studio-communications.index', $studio), $html);
            $this->assertStringContainsString('Was this added incorrectly?', $html);
            $this->assertStringContainsString('release them to families', $html);
            $this->assertStringContainsString('Studio Hub', view($message->view['text'], $message->viewData)->render());

            return true;
        });
    }

    public function test_new_account_invitation_explains_reason_and_registration_but_does_not_claim_active_access(): void
    {
        $studio = Studio::factory()->create(['name' => 'Energy Dance Studio']);
        $notifiable = (new AnonymousNotifiable)->route('mail', 'new@example.test');
        $message = (new StudioAccountInvitation($studio, false, false, null, 'office_contact'))->toMail($notifiable);
        $html = view($message->view['html'], $message->viewData)->render();
        $this->assertStringContainsString('DancePro has added you as a contact', $html);
        $this->assertStringContainsString('Create a DancePro account and verify', $html);
        $this->assertStringContainsString(e(route('customer.register', ['email' => 'new@example.test'])), $html);
        $this->assertStringNotContainsString('Your Studio Hub is ready', $html);
        $this->assertStringNotContainsString('release them to families', $html);
        $this->assertStringContainsString('Was this added incorrectly?', $html);
    }

    public function test_existing_unverified_account_is_asked_to_sign_in_and_verify(): void
    {
        $studio = Studio::factory()->create();
        $message = (new StudioAccountInvitation($studio, true, false, 'Alex', 'director'))
            ->toMail((new AnonymousNotifiable)->route('mail', 'alex@example.test'));
        $html = view($message->view['html'], $message->viewData)->render();
        $this->assertStringContainsString('Sign in to your existing DancePro account and verify', $html);
        $this->assertStringNotContainsString('Create your DancePro account', $html);
        $this->assertStringContainsString('Hi Alex,', $html);
    }
}
