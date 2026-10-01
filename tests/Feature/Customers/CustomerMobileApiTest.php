<?php

namespace Tests\Feature\Customers;

use App\Features\Auth\Support\TokenAbility;
use App\Features\Customers\Models\CustomerCompetitionRegistration;
use App\Features\Customers\Models\CustomerDancer;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CustomerMobileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_customer_api_supports_registration_verification_and_media_access(): void
    {
        Notification::fake();
        $this->postJson(route('api.v1.customer.register'), [
            'name' => 'Mobile Customer', 'email' => 'mobile@example.test',
            'password' => 'long-mobile-password', 'password_confirmation' => 'long-mobile-password',
            'policy_accepted' => true,
        ])->assertCreated()->assertJsonPath('data.email_verified', false);
        $user = User::where('email', 'mobile@example.test')->firstOrFail();
        $this->assertSame('mobile', $user->customerProfile->registration_source);
        Notification::assertSentTo($user, VerifyEmail::class);

        $login = $this->postJson(route('api.v1.customer.auth.login'), [
            'email' => 'mobile@example.test', 'password' => 'long-mobile-password', 'device_name' => 'iPhone',
        ])->assertOk()->assertJsonPath('data.user.email_verified', false);
        $token = $login->json('data.token');
        $this->withToken($token)->getJson(route('api.v1.customer.media'))->assertForbidden()->assertJsonPath('errors.onboarding.0', 'required');

        $verify = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->withoutHeader('Authorization');
        Auth::forgetGuards();
        Auth::shouldUse('web');
        $this->get($verify)->assertRedirect(route('login'));
        Auth::forgetGuards();
        $this->withToken($token)->getJson(route('api.v1.customer.auth.me'))->assertOk()->assertJsonPath('data.email_verified', true);
        $this->withToken($token)->getJson(route('api.v1.customer.media'))->assertOk()->assertJsonStructure(['data' => ['events', 'recommendations', 'purchases']]);
        $this->withToken($token)->postJson(route('api.v1.customer.auth.logout'))->assertOk();
        Auth::forgetGuards();
        $this->withToken($token)->getJson(route('api.v1.customer.auth.me'))->assertUnauthorized();
    }

    public function test_customer_token_is_separate_from_staff_and_policy_onboarding_is_enforced(): void
    {
        $customer = User::factory()->customer()->create();
        $customer->customerProfile()->create(['registration_source' => 'web']);
        $token = $customer->createToken('customer-mobile:Android', [TokenAbility::CustomerMobile->value])->plainTextToken;
        $this->withToken($token)->getJson(route('api.v1.customer.media'))->assertForbidden();
        $this->withToken($token)->postJson(route('api.v1.customer.policy'), ['policy_accepted' => true])->assertOk()->assertJsonPath('data.policy_accepted', true);
        $this->withToken($token)->postJson(route('api.v1.customer.profile'), ['action' => 'dancer', 'name' => 'Jessica Smith'])->assertOk();
        $this->assertSame('Jessica Smith', CustomerDancer::where('user_id', $customer->id)->firstOrFail()->name);
        $this->withToken($token)->getJson(route('api.v1.customer.media'))->assertOk()->assertJsonPath('data.dancers.0.name', 'Jessica Smith');

        $staff = User::factory()->staff()->create();
        $staffToken = $staff->createToken('staff', [TokenAbility::AccountRead->value])->plainTextToken;
        Auth::forgetGuards();
        $this->withToken($staffToken)->getJson(route('api.v1.customer.media'))->assertForbidden();
        $this->postJson(route('api.v1.customer.auth.login'), ['email' => $staff->email, 'password' => 'password', 'device_name' => 'iPad'])->assertForbidden();
    }

    public function test_mobile_competition_gallery_requires_this_accounts_event_registration(): void
    {
        $customer = User::factory()->customer()->create();
        $customer->customerProfile()->create(['registration_source' => 'mobile', 'terms_accepted_at' => now(),
            'privacy_accepted_at' => now(), 'policy_version' => config('customer.policy_version')]);
        $first = SchedulingEvent::create(['name' => 'My competition', 'event_type' => 'competition', 'event_date' => '2026-09-04']);
        $other = SchedulingEvent::create(['name' => 'Another competition', 'event_type' => 'competition', 'event_date' => '2026-09-05']);
        CustomerCompetitionRegistration::create(['user_id' => $customer->id, 'scheduling_event_id' => $first->id, 'access_verified_at' => now()]);
        $token = $customer->createToken('customer-mobile:iPhone', [TokenAbility::CustomerMobile->value])->plainTextToken;

        $this->withToken($token)->getJson(route('api.v1.customer.competitions.show', $first))->assertOk()
            ->assertJsonPath('data.event.uuid', $first->uuid);
        $this->withToken($token)->getJson(route('api.v1.customer.competitions.show', $other))->assertForbidden();
    }
}
