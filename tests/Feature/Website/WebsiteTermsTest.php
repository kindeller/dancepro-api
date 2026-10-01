<?php

namespace Tests\Feature\Website;

use App\Features\Media\Services\MediaCommerceSettings;
use App\Features\Website\Models\WebsitePage;
use App\Features\Website\Services\WebsiteTerms;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteTermsTest extends TestCase
{
    use RefreshDatabase;

    public function test_help_toggle_and_terms_categories_are_available_to_guests_and_customers(): void
    {
        $this->get('/help')->assertOk()->assertSee('Help and terms')->assertSee('Terms &amp; Conditions', false);
        foreach (WebsiteTerms::TABS as $tab => $entry) {
            $page = WebsitePage::where('slug', $entry['slug'])->firstOrFail();
            $this->get('/terms?tab='.$tab)->assertOk()->assertSee(e($page->published['heading']), false)
                ->assertSee('Terms categories')->assertSee('aria-current="page"', false);
        }
        $this->get('/privacy')->assertOk()->assertSee('Choices, access &amp; correction', false);
        $this->actingAs(User::factory()->customer()->create())->get(route('customer.media.help'))
            ->assertOk()->assertSee('Help and terms');
        $this->get('/terms?tab=discounts')->assertOk()->assertSee(route('customer.media.help').'#website-content', false);
    }

    public function test_discount_terms_use_current_tiers_and_describe_studio_grouping(): void
    {
        app(MediaCommerceSettings::class)->save(['bulk_1_threshold' => 12345, 'bulk_1_percent' => 7]);
        $this->get('/terms?tab=discounts')->assertOk()->assertSee('$123.45')->assertSee('Save 7%')
            ->assertSee('Each competition event has its own eligible total')
            ->assertSee('including photos from different concerts and years')
            ->assertSee('before its bulk-discount threshold');
    }

    public function test_terms_editor_keeps_drafts_private_and_honours_hidden_pages(): void
    {
        $page = WebsitePage::where('slug', 'terms-discounts')->firstOrFail();
        $input = [...$page->draft, 'heading' => 'Private discount draft', 'action' => 'draft'];
        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.website.pages.update', $page), $input)->assertSessionHasNoErrors();
        $this->get('/terms?tab=discounts')->assertOk()->assertDontSee('Private discount draft');
        $this->get(route('admin.website.pages.preview', $page))->assertOk()->assertSee('Private discount draft');
        $this->patch(route('admin.website.pages.update', $page), [...$input, 'action' => 'publish'])->assertSessionHasNoErrors();
        $this->get('/terms?tab=discounts')->assertOk()->assertSee('Private discount draft');
        $this->patch(route('admin.website.pages.update', $page), [...$input, 'action' => 'hide'])->assertSessionHasNoErrors();
        $this->get('/terms?tab=discounts')->assertNotFound();
        $this->get('/terms')->assertOk()->assertDontSee('tab=discounts');
        $this->get('/terms?tab=unknown')->assertNotFound();
        $this->get('/terms?tab[]=discounts')->assertNotFound();
    }

    public function test_content_migration_preserves_customised_pages_on_apply_and_rollback(): void
    {
        $page = WebsitePage::where('slug', 'terms-family')->firstOrFail();
        $original = $page->draft;
        $page->update(['draft' => [...$original, 'heading' => 'Owner edited draft']]);
        $migration = require database_path('migrations/2026_10_01_000000_add_website_terms_pages.php');
        $migration->up();
        $this->assertSame('Owner edited draft', $page->fresh()->draft['heading']);
        $migration->down();
        $this->assertModelExists($page);
        $this->assertSame($original, $page->fresh()->published);
        $this->assertFalse(WebsitePage::where('slug', 'terms-discounts')->exists());
        $this->assertTrue(WebsitePage::where('slug', 'terms')->exists());
        $migration->up();
        $this->assertTrue(WebsitePage::where('slug', 'terms-discounts')->exists());
    }
}
