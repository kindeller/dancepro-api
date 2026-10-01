<?php

namespace Tests\Feature\Website;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_menu_has_the_same_order_on_website_gallery_and_sign_in_pages(): void
    {
        foreach (['/', '/galleries', '/services', '/about', '/help', '/login', '/customer/register'] as $url) {
            $response = $this->get($url)->assertOk();
            $this->assertSame(['Home', 'Media galleries', 'Services', 'About', 'Help', 'Sign in'], $this->labels($response->getContent()));
        }
    }

    public function test_customer_and_admin_menu_order_and_nested_page_active_states(): void
    {
        foreach ([User::factory()->customer()->create(), User::factory()->admin()->create()] as $user) {
            $this->actingAs($user);
            foreach (['/', '/about', '/services', '/help', '/my/media', '/my/messages', '/my/profile', '/my/media/help'] as $url) {
                $response = $this->get($url)->assertOk();
                $this->assertSame(['Home', 'My Media', 'My Messages', 'Services', 'About', 'Help', 'My Profile'], $this->labels($response->getContent()));
            }
        }
        foreach (['/my/media/help' => 'Help', '/my/media/orders' => 'My Media', '/book' => 'Services'] as $url => $label) {
            $dom = $this->navigation($this->get($url)->assertOk()->getContent());
            $active = $dom->query('//nav[@aria-label="Main navigation"]/a[@aria-current="page"]');
            $this->assertCount(1, $active);
            $this->assertSame($label, trim($active->item(0)->textContent));
        }
    }

    private function labels(string $html): array
    {
        $links = $this->navigation($html)->query('//nav[@aria-label="Main navigation"]/a');

        return array_map(fn ($link) => trim($link->textContent), iterator_to_array($links));
    }

    private function navigation(string $html): \DOMXPath
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        foreach ($xpath->query('//nav[@aria-label="Main navigation"]//span[contains(concat(" ", normalize-space(@class), " "), " inbox-badge ")]') as $badge) {
            $badge->parentNode->removeChild($badge);
        }

        return $xpath;
    }
}
