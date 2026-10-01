<?php

namespace Tests\Feature\Website;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Customers\Models\CustomerHelpArticle;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Website\Models\WebsitePage;
use App\Features\Website\Services\WebsiteContent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_website_and_help_work_without_an_account(): void
    {
        foreach (['website.home', 'website.about', 'website.services', 'website.help'] as $route) {
            $this->get(route($route))->assertOk()->assertSee('Main navigation')->assertSee('Sign in');
        }
        $this->get('/faq')->assertRedirect('/help');
        $this->get('/help?q=Sharing+concert+videos')->assertOk()->assertSee('three download requests')->assertDontSee('Did this answer your question?');
    }

    public function test_pages_require_admin_and_drafts_are_not_public_until_published(): void
    {
        $page = WebsitePage::where('slug', 'home')->firstOrFail();
        $this->actingAs(User::factory()->customer()->create())->get(route('admin.website.pages.index'))->assertForbidden();
        $this->get(route('admin.website.pages.preview', $page))->assertForbidden();
        $input = [...$page->draft, 'heading' => 'Fresh website heading', 'action' => 'draft'];
        $this->patch(route('admin.website.pages.update', $page), $input)->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->patch(route('admin.website.pages.update', $page), $input)->assertSessionHasNoErrors();
        $this->get('/')->assertDontSee('Fresh website heading');
        $this->get(route('admin.website.pages.preview', $page))->assertOk()->assertSee('Fresh website heading')->assertSee('Draft preview');
        $this->patch(route('admin.website.pages.update', $page), [...$input, 'action' => 'publish'])->assertSessionHasNoErrors();
        $this->get('/')->assertSee('Fresh website heading');
        $this->patch(route('admin.website.pages.update', $page), [...$input, 'action' => 'hide'])->assertSessionHasNoErrors();
        $this->get('/')->assertNotFound();
    }

    public function test_page_links_reject_executable_urls_and_text_is_escaped(): void
    {
        $page = WebsitePage::where('slug', 'about')->firstOrFail();
        $input = [...$page->draft, 'action' => 'publish', 'sections' => [['title' => 'Example', 'body' => '<script>alert(1)</script>', 'link_url' => 'javascript:alert(1)', 'link_label' => 'Open', 'visible' => 1]]];
        $this->actingAs(User::factory()->admin()->create())->patch(route('admin.website.pages.update', $page), $input)->assertSessionHasErrors('sections.0.link_url');
        $input['sections'][0]['link_url'] = '/galleries';
        $this->patch(route('admin.website.pages.update', $page), $input)->assertSessionHasNoErrors();
        $this->get('/about')->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_upcoming_events_are_automatic_hide_cancelled_and_past_and_exclude_internal_details(): void
    {
        $future = SchedulingEvent::create(['name' => 'Future Competition', 'event_type' => 'competition', 'event_date' => today()->addDays(5), 'admin_notes' => 'PRIVATE STAFF NOTES', 'organiser_email' => 'private@example.test']);
        $past = SchedulingEvent::create(['name' => 'Past Competition', 'event_type' => 'competition', 'event_date' => today()->subDays(5)]);
        $cancelled = SchedulingEvent::create(['name' => 'Cancelled Competition', 'event_type' => 'competition', 'event_date' => today()->addDays(5), 'cancelled_at' => now()]);
        $this->get('/')->assertSee('Future Competition');
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.website.events.index'))->assertOk();
        foreach ([$future, $past] as $event) {
            $this->patch(route('admin.website.events.update', $event), ['website_visible' => 1])->assertSessionHasNoErrors();
        }
        $this->patch(route('admin.website.events.update', $cancelled), ['website_visible' => 1])->assertSessionHasErrors('website_visible');
        $this->get('/')->assertSee('Future Competition')->assertDontSee('Past Competition')->assertDontSee('Cancelled Competition')->assertDontSee('PRIVATE STAFF NOTES')->assertDontSee('private@example.test');
        $this->patch(route('admin.website.events.update', $future), ['website_visible' => 0])->assertSessionHasNoErrors();
        $this->get('/')->assertDontSee('Future Competition');
    }

    public function test_bio_approval_uses_a_snapshot_and_new_crew_changes_require_review(): void
    {
        $crew = CrewProfile::factory()->create(['preferred_name' => 'Website Crew', 'biography' => 'Original crew biography']);
        $service = app(WebsiteContent::class);
        $this->assertSame(1, $service->pendingBios());
        $this->get('/about')->assertDontSee('Original crew biography');
        $input = ['name' => 'Public first name', 'role' => 'Photographer', 'body' => 'Approved biography text', 'source_hash' => hash('sha256', $crew->biography), 'sort_order' => 0, 'action' => 'publish'];
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.website.bios.edit', $crew))->assertOk()->assertSee('Original crew biography');
        $this->patch(route('admin.website.bios.update', $crew), $input)->assertSessionHasNoErrors();
        $this->assertSame(0, $service->pendingBios());
        $this->get('/about')->assertSee('Approved biography text')->assertDontSee('Original crew biography')->assertDontSee($crew->phone);
        $crew->update(['biography' => 'Changed by crew']);
        $this->assertSame(1, $service->pendingBios());
        $this->get('/about')->assertSee('Approved biography text')->assertDontSee('Changed by crew');
        $this->patch(route('admin.website.bios.update', $crew), $input)->assertSessionHasErrors('body');
        $this->patch(route('admin.website.bios.update', $crew), [...$input, 'source_hash' => hash('sha256', 'Changed by crew'), 'action' => 'hide'])->assertSessionHasNoErrors();
        $this->get('/about')->assertDontSee('Approved biography text');
    }

    public function test_inactive_crew_are_not_public_and_crew_cannot_approve_their_own_bio(): void
    {
        $user = User::factory()->crew()->create();
        $crew = CrewProfile::factory()->for($user)->create(['biography' => 'Crew text']);
        $input = ['name' => 'Crew', 'role' => 'Crew', 'body' => 'Public biography', 'source_hash' => hash('sha256', $crew->biography), 'sort_order' => 0, 'action' => 'publish'];
        $this->actingAs($user)->patch(route('admin.website.bios.update', $crew), $input)->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->patch(route('admin.website.bios.update', $crew), $input)->assertSessionHasNoErrors();
        $user->update(['is_active' => false]);
        $this->get('/about')->assertDontSee('Public biography');
    }

    public function test_help_drafts_publish_to_both_help_pages_and_can_be_removed(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.website.help.create'))->assertOk();
        $input = ['category' => 'Concert help', 'title' => 'A new helpful answer', 'summary' => 'Brief answer', 'body' => 'Full useful instructions', 'keywords' => 'testing', 'sort_order' => 1, 'action' => 'draft'];
        $this->post(route('admin.website.help.store'), $input)->assertSessionHasNoErrors();
        $article = CustomerHelpArticle::where('title', $input['title'])->firstOrFail();
        $this->get('/help')->assertDontSee('A new helpful answer');
        $this->patch(route('admin.website.help.update', $article), [...$input, 'action' => 'publish'])->assertSessionHasNoErrors();
        foreach (['/help', route('customer.media.help')] as $url) {
            $this->get($url)->assertOk()->assertSee('A new helpful answer');
        }
        $this->patch(route('admin.website.help.update', $article), [...$input, 'body' => 'Still a private draft', 'action' => 'draft'])->assertSessionHasNoErrors();
        $this->get('/help')->assertSee('Full useful instructions')->assertDontSee('Still a private draft');
        $this->patch(route('admin.website.help.update', $article), [...$input, 'action' => 'hide'])->assertSessionHasNoErrors();
        $this->get('/help')->assertDontSee('A new helpful answer');
    }

    public function test_admin_menu_order_and_separate_hub_row(): void
    {
        $admin = User::factory()->admin()->create();
        CrewProfile::factory()->for($admin)->create();
        $this->actingAs($admin)->get(route('admin.website.pages.index'))->assertOk()->assertSeeInOrder(['Media management', 'Website Management', 'Sales &amp; reports'], false);
        foreach (['pages.index', 'events.index', 'bios.index', 'help.index', 'help.create'] as $route) {
            $this->get(route('admin.website.'.$route))->assertOk();
        }
        $this->get(route('admin.website.pages.edit', WebsitePage::first()))->assertOk()->assertSee('Add section');
        $response = $this->get(route('customer.media.index'))->assertOk();
        $doc = new \DOMDocument;
        @$doc->loadHTML($response->getContent());
        $xpath = new \DOMXPath($doc);
        $this->assertStringNotContainsString('Admin Hub', $xpath->query('//nav[@aria-label="Main navigation"]')->item(0)->textContent);
        $this->assertStringContainsString('Admin Hub', $xpath->query('//nav[@aria-label="Your hubs"]')->item(0)->textContent);
        $this->assertStringContainsString('Crew Hub', $xpath->query('//nav[@aria-label="Your hubs"]')->item(0)->textContent);
    }
}
