<?php

namespace Tests\Feature\Website;

use App\Features\Customers\Models\CustomerHelpArticle;
use App\Features\Media\Models\MediaCommerceSetting;
use App\Features\Website\Services\CustomerFeatureGuides;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerFeatureGuidesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guides_are_public_searchable_and_linked_from_customer_pages(): void
    {
        $this->get('/help')->assertOk()->assertSee('Help topics')
            ->assertSee('id="article-guide-studio-review-guide"', false)
            ->assertSee('three download requests')->assertSee('My Events, My Purchases and My Orders');
        $this->get('/help?q=corrections')->assertOk()->assertSee('Review, request corrections')
            ->assertDontSee('id="article-guide-account-setup"', false);
        $this->get('/')->assertOk()->assertSee('#article-guide-media-account-guide', false);
        $this->get('/galleries')->assertOk()->assertSee('#article-guide-gallery-updates', false);

        $this->actingAs(User::factory()->customer()->create());
        $this->get(route('customer.media.help'))->assertOk()->assertSee('Account security');
        $this->get(route('customer.media.index', ['tab' => 'purchases']))->assertOk()->assertSee('#article-guide-download-history', false);
        $this->get(route('customer.media.orders.index'))->assertOk()->assertSee('#article-guide-media-account-guide', false);
        $this->get(route('customer.messages.index'))->assertOk()->assertSee('#article-guide-messages-guide', false);
    }

    public function test_apply_and_revert_preserve_authored_hidden_and_draft_articles_and_settings(): void
    {
        $service = app(CustomerFeatureGuides::class);
        $edited = CustomerHelpArticle::where('slug', 'guide-account-setup')->firstOrFail();
        $edited->update(['body' => 'Owner explanation']);
        $hidden = CustomerHelpArticle::where('slug', 'guide-gallery-updates')->firstOrFail();
        $hidden->update(['is_published' => false]);
        $draft = CustomerHelpArticle::where('slug', 'guide-download-history')->firstOrFail();
        $draft->update(['draft' => ['title' => 'Owner draft']]);
        $setting = MediaCommerceSetting::create(['settings' => ['help_content' => 'Owner welcome', 'concert_photo' => 1234]]);
        $count = CustomerHelpArticle::count();
        $service->apply();
        $service->apply();
        $this->assertSame($count, CustomerHelpArticle::count());
        $service->apply(revert: true);
        $this->assertSame('Owner explanation', $edited->fresh()->body);
        $this->assertFalse($hidden->fresh()->is_published);
        $this->assertSame(['title' => 'Owner draft'], $draft->fresh()->draft);
        $this->assertNull(CustomerHelpArticle::where('slug', 'guide-studio-review-guide')->first());
        $this->assertSame('Owner welcome', $setting->fresh()->settings['help_content']);
        $this->get('/help')->assertDontSee('id="article-guide-gallery-updates"', false);
    }

    public function test_only_unchanged_help_intro_is_updated_and_can_be_reverted(): void
    {
        $setting = MediaCommerceSetting::create(['settings' => ['help_content' => CustomerFeatureGuides::PREVIOUS_INTRO, 'concert_photo' => 1234]]);
        $service = app(CustomerFeatureGuides::class);
        $service->apply();
        $this->assertSame(CustomerFeatureGuides::INTRO, $setting->fresh()->settings['help_content']);
        $this->assertSame(1234, $setting->fresh()->settings['concert_photo']);
        $service->apply(revert: true);
        $this->assertSame(CustomerFeatureGuides::PREVIOUS_INTRO, $setting->fresh()->settings['help_content']);
    }
}
