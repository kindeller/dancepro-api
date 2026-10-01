<?php

namespace Tests\Feature\Website;

use App\Features\Website\Models\WebsitePage;
use App\Features\Website\Services\HomepageAccountContent;
use App\Features\Website\Services\WebsiteContentRefresh;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_prioritises_sign_in_and_registration_for_guests(): void
    {
        $response = $this->get('/')->assertOk()
            ->assertSee('Your photos, videos and performance memories')
            ->assertSeeInOrder(['Create your account', 'Add your studio or event', 'Access your photos &amp; videos'], false)
            ->assertSee('Concert memories.')->assertSee('Competition moments.')
            ->assertSee(route('customer.register'))->assertSee(route('login'))
            ->assertDontSee('Open My Media')->assertDontSee('Plan your event');
        $this->assertStringContainsString('home-performance', $response->getContent());
        $this->get(route('login'))->assertOk();
        $this->get(route('customer.register'))->assertOk();
    }

    public function test_signed_in_customers_and_admins_have_direct_media_actions(): void
    {
        foreach ([User::factory()->customer()->create(), User::factory()->admin()->create()] as $user) {
            $response = $this->actingAs($user)->get('/')->assertOk()->assertSee('Open My Media')
                ->assertSee(route('customer.media.index', ['tab' => 'events']).'#add-media-heading', false);
            preg_match('/<div class="web-hero-actions">(.*?)<\/div>/s', $response->getContent(), $actions);
            $this->assertStringNotContainsString(route('customer.register'), $actions[1]);
            $this->assertStringNotContainsString(route('login'), $actions[1]);
        }
        $this->get(route('customer.media.index', ['tab' => 'events']))->assertOk()->assertSee('id="add-media-heading"', false);
    }

    public function test_refresh_and_rollback_preserve_images_custom_drafts_and_hidden_pages(): void
    {
        $page = WebsitePage::where('slug', 'home')->firstOrFail();
        $previous = app(WebsiteContentRefresh::class)->content()['pages']['home'];
        $custom = [...$previous, 'heading' => 'Owner heading', 'sections' => [['title' => 'Owner section']], 'image' => 'website/owner.webp'];
        $page->update(['draft' => $custom, 'published' => null]);
        $service = app(HomepageAccountContent::class);
        $service->apply();
        $service->apply();
        $page->refresh();
        $this->assertNull($page->published);
        $this->assertSame($custom['sections'], $page->draft['sections']);
        $this->assertSame('Owner heading', $page->draft['heading']);
        $this->assertSame('website/owner.webp', $page->draft['image']);
        $this->assertStringContainsString('DancePro account', $page->draft['description']);
        $service->apply(revert: true);
        $this->assertEquals($custom, $page->fresh()->draft);
        $this->assertNull($page->fresh()->published);

        $page->update(['draft' => $previous, 'published' => $previous]);
        $service->apply();
        $this->assertCount(3, $page->fresh()->published['sections']);
        $service->apply(revert: true);
        $this->assertEquals($previous, $page->fresh()->published);
    }
}
